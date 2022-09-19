<?php

trait Input
{

    // 交互式读取CLI输入
    public function read()
    {
        return trim($this->climate->input('>')->prompt());
    }

    public function inputDescription($curr)
    {
        $this->climate->br()->inline('[')
                      ->green()->inline('description')
                      ->inline(']:     # 可选/建议，博文描述');

        if ($curr) {
            $this->climate->br()->inline('当前: ')->green()->inline($curr);
        }

        echo "\n";
        $description = $this->read();

        return trim($description);
    }

    public function inputTitle($curr)
    {
        $this->climate->br()->inline('[')
                      ->green()->inline('title')
                      ->inline(']:           # 可选/建议，博文标题');

        if ($curr) {
            $this->climate->br()->inline('当前: ')->green()->inline($curr);
        }

        echo "\n";
        $title = $this->read();

        return trim($title);
    }

    public function inputDate($curr)
    {
        $this->climate->br()->inline('[')
                      ->green()->inline('date')
                      ->inline(']:            # 可选/建议，博文发布时间，格式: ' . date('Y-m-d') . ' 或 Y 表示当天');

        if ($curr) {
            $this->climate->br()->inline('当前: ')->green()->inline($curr);
        }

        READ_DATE:
        echo "\n";
        $date = $this->read();
        $date = trim($date);

        // 如果输入Y或y，即返回当天日期
        if (strtoupper($date) == 'Y') {
            $this->climate->inline(' ☞ ' . date('Y-m-d'));
            return date('Y-m-d');
        }

        if (mb_strlen($date) > 0 && (!strtotime($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/i', $date))) {
            echo '[date]格式不正确, 请重新输入:';
            goto READ_DATE;
        }

        return $date;
    }

    public function inputLastModifyDate($curr)
    {
        $this->climate->br()->inline('[')
                      ->green()->inline('lastmod')
                      ->inline(']:         # 可选/建议，博文更新日期，格式: ' . date('Y-m-d') . ' 或 Y 表示当天');

        if ($curr) {
            $this->climate->br()->inline('当前: ')->green()->inline($curr);
        }

        READ_LAST_MODIFY_DATE:
        echo "\n";
        $date = $this->read();
        $date = trim($date);

        // 如果输入Y或y，即返回当天日期
        if (strtoupper($date) == 'Y') {
            $this->climate->inline(' ☞ ' . date('Y-m-d'));
            return date('Y-m-d');
        }

        if (mb_strlen($date) > 0 && (!strtotime($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/i', $date))) {
            echo '[lastmod]格式不正确, 请重新输入:';
            goto READ_LAST_MODIFY_DATE;
        }

        return $date;
    }

    public function inputSlug($curr)
    {
        $this->climate->br()->inline('[')
                      ->green()->inline('slug')
                      ->inline(']:            # 可选/建议，博文自定义URL，支持字母、数字、中划线, 至少6位');

        if ($curr) {
            $this->climate->br()->inline('当前: ')->green()->inline($curr);
        }

        READ_SLUG:
        echo "\n";
        $slug = $this->read();
        $slug = trim($slug);
        if (mb_strlen($slug) > 0 && !preg_match('/^[a-zA-Z\d\-]{6,}$/i', $slug)) {
            $this->climate->red('[slug]格式不正确, 请重新输入:');
            goto READ_SLUG;
        }
        if (empty($slug) && empty($curr)) {
            $this->climate->red('[slug] 必填!!!');
            goto READ_SLUG;
        }

        return $slug ?: $curr;
    }

    public function inputMoreTagLineNum($curr)
    {
        $this->climate->br()->inline('[')
                      ->green()->inline('Summary 行数')
                      ->inline(']:    # 可选/建议，Summary 行数');

        if ($curr) {
            $this->climate->br()->inline('当前: ')->green()->inline($curr);
        }

        READ_SUMMARY_LINE_NUM:
        echo "\n";
        $lineNum = $this->read();
        $lineNum = trim($lineNum);
        if (mb_strlen($lineNum) > 0 && !preg_match('/^\d+$/i', $lineNum)) {
            echo '[Summary 行数]格式不正确, 请重新输入:';
            goto READ_SUMMARY_LINE_NUM;
        }

        return intval($lineNum);
    }

    public function inputAbsolute($curr)
    {
        $this->climate->br()->inline('[')
                      ->green()->inline('absolute')
                      ->inline(']:        # 可选/建议，在博客中的引用文档/附件是否以绝对路径进行链接')
                      ->br()->inline('                   # 出现图片/附件无法显示时，可设置为true尝试解决')
                      ->br()->inline('                   # 绝对路径(true/1)，相对路径(false/0)');

        $this->climate->br()->inline('当前: ')->green()->inline($curr ? 'true' : 'false');

        READ_ABSOLUTE:
        echo "\n";
        $absolute = trim($this->read());
        if (strlen($absolute) == 0) {
            return (bool)$curr;
        }

        // false
        if (in_array(strtolower($absolute), ['false', 'off', '0'])) {
            return false;
        }

        // true
        if (in_array(strtolower($absolute), ['true', 'on', '1'])) {
            return true;
        }

        $this->climate->red('输入值不符合要求');
        goto READ_ABSOLUTE;
    }

}
