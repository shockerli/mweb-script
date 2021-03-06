<?php

/**
 * README 内容生成
 */
class Generate extends Basic
{

    public function start()
    {
        $articleList = [];
        $articles    = $this->db->select('article', '*', [
            'state' => 1,
        ]);
        foreach ($articles as $row) {
            $docId               = $row['uuid'];
            $row['name']         = $this->parseTitle($this->getMWebDocContent($docId));
            $articleList[$docId] = $row;
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
                    $date      = date('Y-m-d', $info['dateModif']);
                    $content[] = "- `[$date]` [{$info['name']}](docs/$aid.md)";
                }
            }

            $content[] = '';
        }

        // 写入README
        file_put_contents($this->MWebPath . '/README.md', implode("\n", $content));
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
        $docDir = $this->MWebPath . '/docs';

        // 使用du命令
        if (function_exists('popen') && !strstr(PHP_OS, 'WIN')) {
            $handle = popen('du -sh ' . $docDir . ' 2>&1', 'r');
            $read   = stream_get_contents($handle);
            $read   = preg_split('/\s+/', trim($read), 2);
            pclose($handle);

            return array_shift($read);
        }

        // 逐个文件读取(未含附件)
        $totalSize = 0;
        foreach (glob($docDir . '/*.md') as $file) {
            is_file($file) && $totalSize += filesize($file);
        }

        return $this->humanStorageSize($totalSize);
    }

}
