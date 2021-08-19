#!/usr/bin/env bash

echo "提交笔记内容..."

script_path=$(cd `dirname $0`; pwd)
root_path=$(cd `dirname "$script_path"`; pwd)

datetime=$(date +"%Y-%m-%d %H:%M:%S")

# 切换到MWeb根目录
cd $root_path

php "${script_path}/generate.php"

git add --all
git commit -m "新增/修改笔记内容: ${datetime}"
git push -u origin master
