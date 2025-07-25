<?php

class ACMS_POST_Entry_Trash extends ACMS_POST_Trash
{
    function post()
    {
        if (!$eid = idval($this->Post->get('eid'))) {
            die();
        }
        if (!IS_LICENSED) {
            die();
        }

        if (enableApproval(BID, CID)) {
            $entry  = ACMS_RAM::entry($eid);
            if (
                1
                && $entry['entry_approval'] !== 'pre_approval'
                && !sessionWithApprovalAdministrator(BID, CID)
            ) {
                die();
            }
        } elseif (roleAvailableUser()) {
            if (!roleAuthorization('entry_delete', BID, $eid)) {
                die();
            }
        } else {
            if (!sessionWithCompilation()) {
                if (!sessionWithContribution()) {
                    die();
                }
                if (SUID <> ACMS_RAM::entryUser($eid)) {
                    die();
                }
            }
        }
        if (HOOK_ENABLE) {
            Webhook::call(BID, 'entry', 'entry:deleted', [$eid, null]);
        }

        $this->trash($eid);
        ACMS_POST_Cache::clearEntryPageCache($eid); // このエントリのみ削除
        AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーをゴミ箱に移動しました');

        //------
        // Hook
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$eid, null]);
        }
        $this->redirect(acmsLink([
            'bid'   => BID,
            'cid'   => CID,
        ]));
    }
}
