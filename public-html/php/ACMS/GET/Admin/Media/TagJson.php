<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Media;

class ACMS_GET_Admin_Media_TagJson extends ACMS_GET_Admin_Media_ListJson
{
    public function get()
    {
        if (!sessionWithContribution()) {
            die403();
        }
        $sql = $this->buildSql();
        $tag = Media::getMediaTagList($sql);
        Common::responseJson($tag);
    }
}
