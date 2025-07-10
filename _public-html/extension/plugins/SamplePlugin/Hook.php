<?php

namespace Acms\Plugins\SamplePlugin;

class Hook
{
    /**
     * 例: グローバル変数の拡張
     *
     * @param \Field &$globalVars
     * @return void
     */
    public function extendsGlobalVars(\Field &$globalVars): void
    {
//         $globalVars->set('key', 'value');
    }

    /**
     * キャッシュルールに特殊ルールを追加
     *
     * @param string $customRuleString
     * @return void
     */
    public function addCacheRule(string &$customRuleString): void
    {
//        $customRuleString = UA_GROUP; // デバイスによってルールを分ける場合
    }
}
