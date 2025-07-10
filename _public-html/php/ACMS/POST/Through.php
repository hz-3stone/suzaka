<?php

class ACMS_POST_Through extends ACMS_POST
{
    function post()
    {
        if ($takeover = $this->Post->get('throughPost')) {
            $Post = acmsUnserialize($takeover);
            if ($Post instanceof Field_Validation) {
                $this->Post->deleteField('throughPost');
                $Post->overload($this->Post, true);
                $this->Post = $Post;
                $this->Post->deleteField('ajaxUploadImageAccess');
            } else {
                httpStatusCode('400 Bad Request');
                AcmsLogger::error('POSTデータの「throughPost」が復元できません');
                return $this->Post;
            }
        }
        return $this->Post;
    }
}
