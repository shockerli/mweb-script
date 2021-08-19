<?php

class Generate extends Basic
{

    public function start()
    {
        $articleList = [];
        $articles    = $this->db->select('article', '*', [
            'state' => 1,
        ]);
        foreach ($articles as $row) {
            $row['name']               = $this->parseTitle($row['uuid']);
            $articleList[$row['uuid']] = $row;
        }

        $catRef       = [];
        $articleCount = 0;
        foreach ($this->db->select('cat_article', '*') as $row) {
            if (!isset($articleList[$row['aid']])) {
                continue;
            }

            $catRef[$row['rid']][$row['aid']] = $articleList[$row['aid']];
            $articleCount                     += 1;
        }

        $cats = $this->db->select('cat', '*', [
            "ORDER" => ["sort" => "ASC"],
        ]);

        $cats      = $this->tree($cats, 0, 2);
        $content   = [];
        $content[] = '# MWeb 笔记';
        $content[] = '> 本项目系对 Mac 笔记软件 `MWeb` 文档库的备份' . "\n";
        $content[] = '> 共有 `' . count($cats) . '` 个分类，`' . $articleCount . '` 篇笔记, 占用空间 `' . $this->getDocumentSize() .
            '`';
        $content[] = '';
        foreach ($cats as $row) {
            $content[] = str_repeat('#', $row['level']) . ' ' . $row['name'];

            if (!empty($catRef[$row['uuid']])) {
                foreach ($catRef[$row['uuid']] as $aid => $info) {
                    $content[] = "- [{$info['name']}](docs/$aid.md)" . ' `(' . date('Y-m-d', $info['dateModif']) . ')`';
                }
            }

            $content[] = '';
        }

        // 写入README
        file_put_contents(dirname(__DIR__) . '/README.md', implode("\n", $content));
    }


    public function tree($arr, $pid = 0, $level = 0)
    {
        static $list = [];
        foreach ($arr as $key => $val) {
            if ($val['pid'] == $pid) {
                $val['level'] = $level;
                $list[]       = $val;
                unset($arr[$key]);
                $this->tree($arr, $val['uuid'], $level + 1);
            }
        }

        return $list;
    }

    public function getDocumentSize()
    {
        $docDir = dirname(__DIR__) . '/docs';

        if (function_exists('popen') && !strstr(PHP_OS, 'WIN')) {
            $handle = popen('du -sh ' . $docDir . ' 2>&1', 'r');
            $read   = stream_get_contents($handle);
            $read   = preg_split('/\s+/', trim($read), 2);
            pclose($handle);

            return array_shift($read);
        }

        $totalSize = 0;
        foreach (glob($docDir . '/*.md') as $file) {
            is_file($file) && $totalSize += filesize($file);
        }

        return $this->humanFileSize($totalSize);
    }

    public function humanFileSize($bytes, $decimals = 2)
    {
        $sz     = 'BKMGTP';
        $factor = (int)floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz{$factor};
    }

}