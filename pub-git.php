<?php

// 该脚本实现对指定文档输出到GitHub仓库README
// php script/pub-git.php [doc-id]

require __DIR__ . '/vendor/autoload.php';

// 开始执行
(new PubGit())->start();
