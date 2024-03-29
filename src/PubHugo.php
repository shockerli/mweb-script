<?php


use Symfony\Component\Yaml\Yaml;

class PubHugo extends Basic
{

    use Input;

    const TAG_READ_MORE = '<!--more-->';

    /**
     * @var array 解析后的 config.ini 配置数据
     */
    public $config = [
        'ignore_tag' => '博文,博客,Blog,BLOG,blog',
    ];

    /**
     * @var string Hugo 博客根目录
     */
    public $hugoRootPath;

    /**
     * @var string 普通博客文章存放目录名，根据不同主题而异，post/posts
     */
    public $postPath = 'post';

    /**
     * @var string 存放博客文章的绝对实际目录
     */
    public $contentDir;

    /**
     * @var bool 引用文档或附件，是绝对路径or相对路径
     *           相对路径，在首页中，如果有图片，会出现问题
     */
    public $absolute = false;

    public function readConfig()
    {
        parent::readConfig();

        $config       = @parse_ini_file($this->configPath);
        $this->config = array_merge($this->config, $config);
        if (empty($config['hugo_root_path'])) {
            $this->climate->red("配置项: hugo_root_path 缺失");
            exit("\n");
        }

        if (!is_dir($config['hugo_root_path'])) {
            $this->climate->red("Hugo 项目根目录: {$config['hugo_root_path']} 不存在");
            exit("\n");
        }

        if (isset($config['absolute'])) {
            $this->absolute = (bool)$config['absolute'];
        }

        $this->hugoRootPath = rtrim(trim($config['hugo_root_path']), '/');
        if (!empty($config['post_path'])) {
            $this->postPath = trim(trim($config['post_path']), '/');
        }
        $this->contentDir = $this->hugoRootPath . '/content/' . $this->postPath; // 有些主题是post，有些是posts
        if (!is_dir($this->contentDir)) {
            mkdir($this->contentDir, 0777, true);
        }
    }

    /**
     * 开始运行命令行
     */
    public function start()
    {
        $this->router->add('<doc_id:uint>', function (array $args) {
            $docId = $args['doc_id'];
            $this->climate->white()->inline('正在发布: ')->green()->inline($docId)->br();

            $article = $this->db->get('article', '*', [
                'uuid' => $docId,
            ]);
            if (!$article) {
                $this->climate->red("文档: $docId 不存在");
                exit("\n");
            }

            $docFilePath = $this->getMWebDocPath($docId);
            if (!file_exists($docFilePath)) {
                $this->climate->red("文档文件: $docFilePath 不存在");
                exit("\n");
            }

            $header            = [];
            $existHugoFilePath = $this->findHugoPostPath($docId);
            if ($existHugoFilePath) {
                $this->climate->white()->inline('已发布路径: ')->green()->inline(dirname($existHugoFilePath))->br();
                try {
                    $header = Yaml::parse($this->parseYamlContent($existHugoFilePath));
                } catch (Exception $e) {
                }
            }

            // 文档ID(MWeb)
            $header['doc_id'] = $docId;

            // 标题
            if (empty($header['title'])) {
                $header['title'] = $this->parseTitle($this->getMWebDocContent($docId));
            }
            $title = $this->inputTitle($header['title']);
            if ($title) {
                $header['title'] = $title;
            }

            // 描述
            $description = $this->inputDescription($header['description'] ?? '');
            if ($description) {
                $header['description'] = $description;
            }

            // 标签/关键词(强行覆盖)
            $ignoreTags = array_filter(explode(',', $this->config['ignore_tag']));
            $tagList    = array_values(array_filter(array_diff($this->getTag($docId), $ignoreTags)));
            if ($tagList) {
                $header['tags'] = $header['keywords'] = $tagList;
            } else {
                unset($header['tags'], $header['keywords']);
            }
            $this->climate->br()->inline('标签: ')->green(implode(', ', $header['tags'] ?? []));

            // 分类(强行覆盖)
            $header['categories'] = $this->getCategory($docId);
            $this->climate->br()->inline('分类: ')->green(implode(', ', $header['categories'] ?? []));

            // 发布时间
            if (empty($header['date'])) {
                $header['date'] = date('Y-m-d');
            }
            $date = $this->inputDate($header['date']);
            if ($date) {
                $header['date'] = $date;
            }

            // 更新时间
            if (empty($header['lastmod'])) {
                $header['lastmod'] = date('Y-m-d', $article['dateModif']);
            }
            $lastModifyDate = $this->inputLastModifyDate($header['lastmod']);
            if ($lastModifyDate) {
                $header['lastmod'] = $lastModifyDate;
            }
            if (strtotime($header['lastmod']) < strtotime($header['date'])) {
                $header['lastmod'] = $header['date'];
            }

            // URL: /post/xxx
            $slug = $this->inputSlug($header['slug'] ?? '');
            if ($slug) {
                $header['slug'] = $slug;
            }
            if (empty($header['slug'])) {
                $this->climate->bold()->red('slug 缺失');
            }

            // 标签: <!--more-->
            $header['tag_more_line'] = $this->inputMoreTagLineNum($header['tag_more_line'] ?? 0);

            // absolute
            // 优先级:
            // 1. 当前输入
            // 2. 已发布文档的配置
            // 3. config.ini中的配置
            // 4. 代码默认值
            $this->absolute = $this->inputAbsolute($header['absolute'] ?? $this->absolute);
            if ($this->absolute) {
                $header['absolute'] = true;
            } else {
                unset($header['absolute']);
            }

            // 自动标题中的更新日期为
            $header['title'] = preg_replace_callback('#\d{4}[.|-]\d{1,2}[.|-]\d{1,2}#', function () use ($header) {
                return date('Y.m.d', strtotime($header['lastmod']));
            }, $header['title']);

            // 组装内容
            $content = $this->modifyContent($docFilePath, $header);

            ksort($header);
            $headerYaml = Yaml::dump($header);
            $doc        = "---\n# 博文配置信息\n\n" . $headerYaml . "---\n\n\n" . $content;

            // 先删除原Hugo文章目录
            // 因为slug可能变了...
            file_exists($existHugoFilePath) && $this->delPath(dirname($existHugoFilePath));

            // 创建新目录
            $hugoPostDir = "$this->contentDir/{$header['slug']}";
            mkdir($hugoPostDir, 0777, true);

            // 写入文档(约定统一以index.md为文章内容文件，一篇文章一个目录)
            file_put_contents("$hugoPostDir/index.md", $doc);
            $this->climate->br()->white('发布成功:');
            $this->climate->tab()->red($header['title']);
            $this->climate->white("文档路径:");
            $this->climate->tab()->green($hugoPostDir);

            // 复制附件
            $mediaPath = $this->MWebPath . '/docs/media/' . $docId;
            if (is_dir($mediaPath) && !$this->isEmptyDir($mediaPath)) {
                $hugoMediaPath = "$hugoPostDir/media";
                $this->copyRecursive($mediaPath, $hugoMediaPath);
                $this->climate->white("附件路径:");
                $this->climate->tab()->green($hugoMediaPath);
            }

            // 自动添加标签: Blog
            $this->addTag2Blog($docId, 'Blog');
        });

        // 命令使用说明
        $this->router->add('[--help | -h]', function () {
            echo 'Usage help:' . PHP_EOL;
            echo '    php ' . $_SERVER['argv'][0] . ' <doc_id>' . PHP_EOL;
        });

        $this->router->execArgv();
    }

    /**
     * 迁移
     * (仅执行一次)
     * (最好在版本控制下执行，避免内容丢失)
     * 原: 博文内容与附件分离
     * 新: 每篇博文独立一个文件夹
     *
     * @deprecated
     */
    public function migrate()
    {
        foreach (glob($this->contentDir . '/*.md') as $path) {
            $header = Yaml::parse($this->parseYamlContent($path));
            $docId  = (int)$header['doc_id'];
            $slug   = $header['slug'];

            // 创建新目录
            $hugoPostDir = "$this->contentDir/$slug";
            mkdir($hugoPostDir, 0777, true);

            // 写入文档
            $doc = file_get_contents($path);
            $doc = $this->replaceMediaPath($doc, $header);
            file_put_contents("$hugoPostDir/index.md", $doc);

            // 复制附件
            $mediaPath = $this->hugoRootPath . '/static/media/' . $docId;
            if (is_dir($mediaPath)) {
                $hugoMediaPath = "$hugoPostDir/media";
                $this->copyRecursive($mediaPath, $hugoMediaPath);
                $this->climate->green("文档[$docId]迁移完成(media)");
                continue;
            }

            $this->climate->green("文档[$docId]迁移完成");
        }
    }

    /**
     * 根据doc_id查找对应博文的MD文件路径
     *
     * @param  int $docId
     * @return string|null
     */
    public function findHugoPostPath($docId)
    {
        foreach (glob($this->contentDir . '/*/*.md') as $path) {
            $header = Yaml::parse($this->parseYamlContent($path));
            if ($header['doc_id'] == $docId) {
                return $path;
            }
        }

        return null;
    }

    // 从Hugo文件中读取Yaml配置头信息
    public function parseYamlContent($filePath)
    {
        $handle = fopen($filePath, "r");
        if (!$handle) {
            return '';
        }

        $yaml    = "";
        $started = false;
        while (($line = fgets($handle, 4096)) !== false) {
            // 开始&结束标识符
            if (stripos($line, '---') === 0) {
                if (!$started) {
                    $started = true;
                    continue;
                } else {
                    break;
                }
            }

            $yaml .= $line;
        }
        fclose($handle);

        return $yaml;
    }

    /**
     * 对笔记内容进行解析转换
     *
     * @param  string  $filePath MWeb的笔记文件路径
     * @param  array  &$header   文档头信息
     * @return string
     */
    public function modifyContent($filePath, &$header)
    {
        $handle = fopen($filePath, "r");
        if (!$handle) {
            return '';
        }

        $content    = "";
        $find       = false;
        $hasTagMore = 0;
        $num        = 0;
        while (($line = fgets($handle, 4096)) !== false) {
            $num += 1;

            // 开始&结束标识符
            if (!$find) {
                $line = trim($line);
                if (preg_match('/^#[^#]+/i', $line)) {
                    $find = true;
                    continue;
                }
            }

            // flow
            if (trim($line) == '```flow') {
                $line = '```flowchart' . PHP_EOL;
            }

            // Hugo 不认带有空格的标签，比如 <!-- more --> 就不行，MWeb 自带的就是这种，不行，需要替换
            if (preg_match('/^\s*<!--\s*more\s*-->\s*$/i', $line)) {
                $hasTagMore++;
                $header['tag_more_line'] = $num; // 修改行号为已有
                // 第一个标签，替换为HUGO标准
                if ($hasTagMore == 1) {
                    $line = PHP_EOL . self::TAG_READ_MORE . PHP_EOL;
                } else {
                    // 其余的跳过忽略
                    continue;
                }
            }

            // 切记: 不能去空
            $content .= $line;

            // 如果没有、且符合行号，则添加<!--more-->标签
            if (!empty($header['tag_more_line']) && $num == $header['tag_more_line'] && $hasTagMore == 0) {
                $content .= PHP_EOL . "<!--more-->" . PHP_EOL . PHP_EOL;
            }
        }

        fclose($handle);

        $content = ltrim($content);

        // 替换变量内容
        $content = $this->replaceVars($content, $this->vars());

        // 替换附件路径
        $content = $this->replaceMediaPath($content, $header);
        // 替换关联文档路径
        return $this->replaceMWebLink($content, $header);
    }

    /**
     * 替换附件路径
     *
     * @param  string $doc    文档笔记内容
     * @param  array  $header 文档头信息
     * @return string
     */
    public function replaceMediaPath($doc, $header)
    {
        // 绝对路径，是为了兼容Summary
        // 支持引用其他文档的附件

        // (绝对)
        // ](media/xx/yy.zz)   =>  ](/post/xx/media/yy.zz)
        // ](/media/xx/yy.zz)  =>  ](/post/xx/media/yy.zz)

        // (相对)
        // ](media/xx/yy.zz)   =>  ](media/yy.zz)
        // ](media/xx/yy.zz)   =>  ](../xx/media/yy.zz)
        // ](/media/xx/yy.zz)  =>  ](media/yy.zz)
        // ](/media/xx/yy.zz)  =>  ](../xx/media/yy.zz)
        return preg_replace_callback('#(]\()/?(media/)(\d+)/(.*\))#', function ($matches) use ($header) {
            $slug = $header['slug'];
            // 引用其他文档的附件时
            if ($matches[3] != $header['doc_id']) {
                $existPath = $this->findHugoPostPath($matches[3]);
                if (!$existPath) {
                    $this->climate->red('引用的附件所在文档尚未发布: ' . $matches[0]);
                    exit;
                }
                $slug = basename(dirname($existPath));
            }
            $slug = trim($slug, '/');

            // 构建路径
            if ($this->absolute) { // 绝对路径
                return urldecode($matches[1] . '/' . $this->postPath . '/' . $slug . '/' . $matches[2] . $matches[4]);
            } // 其他文档的附件
            else if ($matches[3] != $header['doc_id']) {
                return urldecode($matches[1] . '../' . $slug . '/' . $matches[2] . $matches[4]);
            } else { // 自身附件
                return urldecode($matches[1] . $matches[2] . $matches[4]);
            }
        }, $doc);
    }

    /**
     * 替换mweblib相关文档链接
     *
     * @param  string $doc    文档笔记内容
     * @param  array  $header 文档头信息
     * @return string
     */
    public function replaceMWebLink($doc, $header)
    {
        // [XXX](mweblib://12345678)  => ../blog-slug/      (相对)
        // [XXX](mweblib://12345678)  => /post/blog-slug/   (绝对)
        return preg_replace_callback('#mweblib://(\d+)#', function ($matches) use ($header) {
            // 绝对or相对
            $prefix = '/' . $this->postPath;
            if (!$this->absolute) {
                $prefix = '..';
            }

            $docId = $matches[1];
            // 引用了自己...
            if ($docId == $header['doc_id']) {
                return $prefix . '/' . $header['slug'];
            }

            // 引用了其他文档，查询是否已发布
            $existPath = $this->findHugoPostPath($docId);
            if (!$existPath) {
                $this->climate->red('笔记中引用的文档尚未发布成博客: ' . $matches[0]);
                $this->climate->green('请先发布引用的文档');
                exit;
            }

            // 替换成绝对路径的链接
            // 相对路径，在Summary时会有问题
            return $prefix . '/' . basename(dirname($existPath));
        }, $doc);
    }

}
