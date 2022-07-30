# mweb-script

> [MWeb](https://zh.mweb.im) 笔记管理软件的常用脚本，支持发布文章到 [Hugo](https://gohugo.io) 博客

## 安装

环境要求:

- Git
- PHP >= 7.1
- Composer
- macOS（MWeb 仅支持 Mac）

在 MWeb 文档存放目录下，执行：

```shell
git submodule add git@github.com:shockerli/mweb-script.git script
```

将以子模块的方式安装到当前 `script` 目录下。

如果没有用 Git 对 MWeb 文档进行管理，那么直接下载本项目的代码，放到 `script` 目录即可。

> `script` 非必须，可以是其他名字，此处是为了方便后续文档统一书写。

对于主项目全新克隆时，需初始化并更新子项目，这就会自动克隆子项目：
```shell
git submodule init && git submodule update
```

然后到 `script` 目录下执行 `composer install --no-dev -vv`。

## 配置

> 发布 Hugo 博客文章才需配置

复制 `config.ini.example` 为 `config.ini` 并修改其中的配置：

```ini
# Hugo博客根目录
hugo_root_path = /path/to/your/hugo/blog
# 列表文章的目录，一般为post或posts，不同主题有所不同
# 可选，默认为post
post_path = post
# 引用的文档/附件是绝对路径or相对路径
# 可选，默认为false（相对路径）
absolute = false
# 忽略的标签，不会同步到Hugo
# 可选，半角逗号分隔多个，默认：博文,博客,Blog,BLOG,blog
ignore_tag = 博文,博客,Blog,BLOG,blog
```

## 脚本 `commit.sh`

> - 统计笔记数据，并输出到 `README.md`
>
> - 提交到 Git 版本库，并推送到远程仓库

使用方法：

```shell
# 终端中，在MWeb文档库根目录执行
./script/commit.sh
```

## 脚本 `pub-hugo.php`

> - 交互式脚本
>
> - 读取指定笔记内容
>
> - 自动生成 Hugo 博文配置信息（标题、描述、Slug、Summery 分隔符、分类、标签等）
>
> - 自动拷贝笔记中的附件到博客目录，并替换笔记内容中的附件链接地址
>
> - 自动替换 MWeb 内链 `[XXX](mweblib://12345678)  => ../blog-slug/`
>
> - 支持绝对路径、相对路径可选（优先级：当前输入 > 博文配置 > config.ini配置 > 默认false）如果首页图片加载出问题，可输入true解决
>
> - 自动忽略 `博文`、`博客`、`Blog`、`BLOG`、`blog` 标签，也可在配置中设置

在终端中执行 `php script/pub-hugo.php [doc-id]`，进入交互式命令行：

```shell
正在发布: 15997237847994
已发布路径: /Users/jioby/shockerli.net/content/post/php-homebrew-dyld-library-not-load

[title]:           # 可选/建议，博文标题
当前: PHP Homebrew 版本问题 dyld: Library not load
> 

[description]:     # 可选/建议，博文描述
当前: PHP Homebrew 版本问题 dyld: Library not load
> 

标签: Homebrew, PHP

分类: PHP

[date]:            # 可选/建议，博文发布时间，格式: 2021-08-19
当前: 2021-08-16
> 

[lastmod]:         # 可选/建议，博文更新日期，格式: 2021-08-19
当前: 2021-08-16
> 

[slug]:            # 可选/建议，博文自定义URL，支持字母、数字、下划线、中划线, 至少6位
当前: php-homebrew-dyld-library-not-load
> 

[Summary 行数]:    # 可选/建议，Summary 行数
当前: 17
> 

[absolute]:        # 可选/建议，在博客中的引用文档/附件是否以绝对路径进行链接，绝对路径(true/1)，相对路径(false/0)
当前: true
> 

博文[PHP Homebrew 版本问题 dyld: Library not load]发布成功
文档路径: 
        /Users/jioby/shockerli.net/content/post/php-homebrew-dyld-library-not-load
```

最后生成的博客文章内容如下：

```
---
# 博文配置信息

categories:
    - PHP
date: '2021-08-16'
description: 'PHP Homebrew 版本问题 dyld: Library not load'
doc_id: 15997237847994
keywords:
    - PHP
    - Homebrew
lastmod: '2021-08-16'
slug: php-homebrew-dyld-library-not-load
tag_more_line: 17
tags:
    - PHP
    - Homebrew
title: 'PHP Homebrew 版本问题 dyld: Library not load'
---


## 环境
`macOS`、`Homebrew`、`PHP 7.1.x`


<!--more-->


## 起因
通过 `brew` 更新了其他有依赖此 `Library` 的软件

「其他内容省略...」
```


## 脚本 `pub-github.php`

在终端中执行 `php script/pub-github.php [doc-id]` 发布内容到 Git 仓库。

使用前，需在配置文件 `config.ini` 添加 Git 仓库地址映射：

```ini
[pub-github]
# 文档ID = Git仓库地址
15120187670755 = /Users/jioby/code/shockerli/go-awesome
```
