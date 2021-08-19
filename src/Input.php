<?php

trait Input
{

    // 交互式读取CLI输入
    public function read()
    {
        return trim($this->climate->input('>')->prompt());

        // $fp    = fopen('php://stdin', 'r');
        // $input = fgets($fp, 255);
        // fclose($fp);
        // return trim($input);
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
            ->inline(']:            # 可选/建议，博文发布时间，格式: ' . date('Y-m-d'));

        if ($curr) {
            $this->climate->br()->inline('当前: ')->green()->inline($curr);
        }

        READ_DATE:
        echo "\n";
        $date = $this->read();
        $date = trim($date);
        if (mb_strlen($date) > 0 && (!strtotime($date) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/i', $date))) {
            echo '[date]格式不正确, 请重新输入:';
            goto READ_DATE;
        }

        return $date;
    }

    public function inputLastModifyDate($curr)
    {
        $this->climate->br()->inline('[')
            ->green()->inline('lastmod')
            ->inline(']:         # 可选/建议，博文更新日期，格式: ' . date('Y-m-d'));

        if ($curr) {
            $this->climate->br()->inline('当前: ')->green()->inline($curr);
        }

        READ_LAST_MODIFY_DATE:
        echo "\n";
        $date = $this->read();
        $date = trim($date);
        if (mb_strlen($date) > 0 && (!strtotime($date) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/i', $date))) {
            echo '[lastmod]格式不正确, 请重新输入:';
            goto READ_LAST_MODIFY_DATE;
        }

        return $date;
    }

    public function inputSlug($curr)
    {
        $this->climate->br()->inline('[')
            ->green()->inline('slug')
            ->inline(']:            # 可选/建议，博文自定义URL，支持字母、数字、下划线、中划线, 至少6位');

        if ($curr) {
            $this->climate->br()->inline('当前: ')->green()->inline($curr);
        }

        READ_SLUG:
        echo "\n";
        $slug = $this->read();
        $slug = trim($slug);
        if (mb_strlen($slug) > 0 && !preg_match('/^[a-zA-Z0-9\-_]{6,}$/i', $slug)) {
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
        if (mb_strlen($lineNum) > 0 && !preg_match('/^[0-9]+$/i', $lineNum)) {
            echo '[Summary 行数]格式不正确, 请重新输入:';
            goto READ_SUMMARY_LINE_NUM;
        }
        if (empty($lineNum) && empty($curr)) {
            $this->climate->red('[Summary 行数] 必填!!!');
            goto READ_SUMMARY_LINE_NUM;
        }

        return intval($lineNum);
    }

}
