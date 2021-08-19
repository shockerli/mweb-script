<?php

// 该脚本实现对指定文档输出到Hugo
// php script/pub-hugo.php [doc-id]

require __DIR__ . '/vendor/autoload.php';

// 开始执行
(new PubHugo)->start();
