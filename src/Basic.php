<?php

use Clue\Commander\Router;
use League\CLImate\CLImate;
use Medoo\Medoo;

class Basic
{

    public $MWebPath;
    public $dbPath; // sqlite3 数据库文件路径
    public $configPath;

    /**
     * @var CLImate 命令行输出
     */
    public $climate;

    /**
     * @var Router
     */
    public $router;

    /**
     * @var Medoo
     */
    public $db;

    public function __construct()
    {
        $this->MWebPath   = dirname(dirname(__DIR__));
        $this->dbPath     = $this->MWebPath . '/mainlib.db';
        $this->configPath = dirname(__DIR__) . '/config.ini';

        $this->climate = new CLImate;
        $this->router  = new Router();

        $this->connectDB();
        $this->readConfig();
    }

    /**
     * 检查sqlite3数据库地址是否OK，并连接
     */
    public function connectDB()
    {
        if (!file_exists($this->dbPath)) {
            $this->climate->red("MWeb数据库文件: $this->dbPath 不存在");
            exit("\n");
        }
        $this->climate->green();

        $this->db = new Medoo([
            'database_type' => 'sqlite',
            'database_file' => $this->dbPath,
        ]);
    }

    /**
     * 读取&解析配置(建议重写)
     */
    public function readConfig()
    {
        if (!file_exists($this->configPath)) {
            $this->climate->red("配置文件: $this->configPath 不存在");
            exit("\n");
        }
    }

    /**
     * 获取文档路径
     *
     * @param  int $docId 文档ID
     * @return string
     */
    public function getMWebDocPath($docId)
    {
        return $this->MWebPath . '/docs/' . $docId . '.md';
    }

    /**
     * 读取Doc文档内容
     *
     * @param  int $docId 文档ID
     * @return string
     */
    public function getMWebDocContent($docId)
    {
        if (!file_exists($this->getMWebDocPath($docId))) {
            return '';
        }
        return file_get_contents($this->getMWebDocPath($docId));
    }

    /**
     * 从MD文件获取文档标题
     *
     * @param  string $content Markdown 内容
     * @return string
     */
    public function parseTitle($content)
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = trim($content, "\n");
        $lines   = explode("\n", $content);
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            if (preg_match("/^(#+)(.*)$/", $line, $matches)) {
                if (strlen($matches[1]) == 1) {
                    return trim($matches[2]);
                }
            }
        }

        return '';
    }

    /**
     * 替换MD标题
     *
     * @param  string $content  文档内容
     * @param  string $newTitle 新标题
     * @return string
     */
    public function replaceTitle($content, $newTitle)
    {
        return preg_replace_callback("/^(#\s)(.*)(\n)/", function ($matches) use ($newTitle) {
            return $matches[1] . $newTitle . $matches[3];
        }, $content);
    }

    /**
     * 是否为空目录
     *
     * @param  string $dir
     * @return bool
     */
    public function isEmptyDir($dir)
    {
        return empty(glob(rtrim($dir, '/') . '/*'));
    }

    /**
     * 复制目录
     *
     * @param  string $srcDir  源目录
     * @param  string $destDir 目标目录
     */
    public function copyRecursive($srcDir, $destDir)
    {
        $srcDir = rtrim($srcDir, '/');
        if (!is_dir($srcDir)) {
            return;
        }

        $files = glob($srcDir . '/*');
        if (empty($files)) {
            return;
        }

        $destDir = rtrim($destDir, '/');
        if (!is_dir($destDir)) {
            mkdir($destDir, 0777, true);
        }

        foreach ($files as $file) {
            $dst = $destDir . '/' . basename($file);
            if (is_file($file)) {
                copy($file, $dst);
            } else if (is_dir($file)) {
                if (!is_dir($dst)) {
                    mkdir($dst, 0777, true);
                }

                $this->copyRecursive($file, $dst);
            }
        }
    }

    /**
     * 获取文档分类
     *
     * @param  int $docId 文档ID
     * @return array
     */
    public function getCategory($docId)
    {
        $catList = $this->db->select('cat_article', [
            '[>]cat' => ['cat_article.rid' => 'uuid'],
        ], [
            'cat.name',
        ], [
            'cat_article.aid' => $docId,
        ]);

        // 排序，保持一致
        $cats = array_column($catList, 'name');
        sort($cats);

        return $cats;
    }

    /**
     * 获取文档TAG
     *
     * @param  int $docId 文档ID
     * @return array
     */
    public function getTag($docId)
    {
        // MWeb 3.x tag 表中没有uuid，关联表中的rid为tag.id
        // MWeb 4.x tag 表新增了uuid，关联表中的rid为tag.uuid
        $row    = $this->db->get('tag', '*');
        $column = $row['uuid'] ? 'uuid' : 'id';

        $tagList = $this->db->select('tag_article', [
            '[>]tag' => ['tag_article.rid' => $column],
        ], [
            'tag.name',
        ], [
            'tag_article.aid' => $docId,
        ]);

        // 排序，保持一致
        $tags = array_column($tagList, 'name');
        sort($tags);

        return $tags;
    }

    /**
     * 给文章添加标签
     * （标签已存在）
     *
     * @param  int    $docId
     * @param  string $tagName
     * @return bool
     */
    public function addTag2Blog($docId, $tagName)
    {
        $tagName = trim($tagName);

        // 已存在关联
        $tagList = $this->getTag($docId);
        if (in_array(strtolower($tagName), array_map(function ($v) {
            return strtolower($v);
        }, $tagList))) {
            return true;
        }

        // 标签是否存在
        $tag = $this->db->get('tag', '*', ['name' => $tagName]);
        if (!$tag) {
            return false;
        }
        $tagUuid = $tag['uuid'];
        $this->db->insert('tag_article', [
            'rid' => $tagUuid,
            'aid' => $docId,
        ]);
        return true;
    }

    /**
     * 删除路径
     *
     * @param  string $path 文件或目录的路径
     */
    public function delPath($path)
    {
        // 不存在直接返回(可能是没权限)
        if (!file_exists($path)) {
            return;
        }

        // [兼容]如果是文件则直接删除
        if (is_file($path)) {
            unlink($path);
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']); // 扫描目录，并排除.与..
        foreach ($files as $file) {
            (is_dir("$path/$file"))
                ? $this->delPath("$path/$file") // 目录则递归
                : unlink("$path/$file"); // 文件则删除
        }
        rmdir($path); // 目录下文件及子目录删除完后，再删除本目录
    }

    /**
     * 格式化存储大小
     *
     * @param  int $bytes    存储大小(B)
     * @param  int $decimals 保留小数
     * @return string
     */
    public function humanStorageSize($bytes, $decimals = 2)
    {
        $sz     = 'BKMGTP';
        $factor = (int)floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

    /**
     * 模板变量替换
     * 支持的变量根据 $params 的 KEY 而定, 用法: ${var_name} 替换为 $params['var_name'] 的值
     *
     * @param  array|string $subject
     * @param  array        $params
     * @return string|array
     */
    public function replaceVars($subject, array $params)
    {
        $encoded = false;
        if (!is_string($subject)) {
            $encoded = true;
            $subject = json_encode($subject, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $subject = preg_replace_callback(
            [
                '/"(\${([a-zA-Z]\w*?)})"/i',
                '/(\${([a-zA-Z]\w*?)})/i',
            ],
            function ($matches) use ($params) {
                if (!isset($params[$matches[2]])) {
                    return $matches[0];
                }

                $replace = $params[$matches[2]];
                if (!is_scalar($replace)) {
                    $replace = json_encode($replace, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else if ($matches[0][0] == '"') {
                    $replace = '"' . $replace . '"';
                }
                return $replace;
            },
            $subject
        );

        if ($encoded) {
            $subject = json_decode($subject, true);
            if (json_last_error()) {
                $subject = [];
            }
        }

        return $subject;
    }

    public function vars()
    {
        return [
            'date'     => date('Y-m-d'),
            'dateDot'  => date('Y.m.d'),
            'dateZh'   => date('Y年月d日'),
            'time'     => date('H:i:s'),
            'datetime' => date('Y-m-d H:i:s'),
        ];
    }

}
