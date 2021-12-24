<?php

class PubGitHub extends Basic
{

    /**
     * @var array docId => Git仓库绝对路径
     */
    private $map = [];

    public function readConfig()
    {
        parent::readConfig();

        $config = @parse_ini_file($this->configPath);
        foreach ($config as $docId => $path) {
            if (is_numeric($docId)) {
                $this->map[$docId] = $path;
            }
        }
    }

    public function start()
    {
        $this->router->add('<doc_id:uint>', function (array $args) {
            $docId = $args['doc_id'];
            $this->climate->white()->inline('正在发布: ')->green()->inline($docId)->br();

            $docFilePath = $this->getMWebDocPath($docId);
            if (!file_exists($docFilePath)) {
                $this->climate->error("文档文件: $docFilePath 不存在");
                exit("\n");
            }

            $article = $this->db->get('article', '*', [
                'uuid' => $docId,
            ]);
            if (!$article) {
                $this->climate->error("文档: $docId 不存在");
                exit("\n");
            }

            // 读取文档
            $content = file_get_contents($docFilePath);

            $title = $this->parseTitle($content);
            $this->climate->white()->inline('文章标题: ')->green()->inline($title)->br();

            $gitPath = $this->map[$docId] ?? '';
            if (empty($gitPath)) {
                $this->climate->error("文档: $docId 未配置 GitHub 仓库地址");
                exit("\n");
            }
            $this->climate->white()->inline('Git仓库:  ')->green()->inline($gitPath)->br();
            if (!file_exists($gitPath)) {
                $this->climate->error("文档: $docId 配置的 GitHub 仓库地址不存在");
                exit("\n");
            }

            $gitReadmePath = $gitPath . '/README.md';
            if (file_exists($gitReadmePath)) {
                $usedTitle = $this->parseTitle(file_get_contents($gitReadmePath));
                $content   = $this->replaceTitle($content, $usedTitle);
            }

            // 写入文档
            file_put_contents($gitReadmePath, $content);

            // 复制附件
            $mediaPath = $this->MWebPath . '/docs/media/' . $docId;
            if (is_dir($mediaPath) && !$this->isEmptyDir($mediaPath)) {
                $gitAssetsPath = "$gitPath/assets";
                $this->copyRecursive($mediaPath, $gitAssetsPath);
                $this->climate->white("附件路径: ");
                $this->climate->tab()->green($gitAssetsPath);
            }
        });

        $this->router->add('[--help | -h]', function () {
            echo 'Usage help:' . PHP_EOL;
            echo '    php ' . $_SERVER['argv'][0] . ' <doc_id>' . PHP_EOL;
        });

        $this->router->execArgv();
    }

}
