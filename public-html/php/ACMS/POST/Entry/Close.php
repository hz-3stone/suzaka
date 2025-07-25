<?php

class ACMS_POST_Entry_Close extends ACMS_POST_Entry
{
    function post()
    {
        if (!$eid = idval($this->Post->get('eid'))) {
            die();
        }
        if (!IS_LICENSED) {
            die();
        }
        if (!sessionWithCompilation()) {
            if (!sessionWithContribution()) {
                die();
            }
            if (SUID <> ACMS_RAM::entryUser($eid)) {
                die();
            }
        }

        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newUpdate('entry');
        $SQL->setUpdate('entry_status', 'close');
        $SQL->addWhereOpr('entry_id', $eid);
        $DB->query($SQL->get(dsn()), 'exec');
        ACMS_RAM::entry($eid, null);
        ACMS_POST_Cache::clearEntryPageCache($eid); // このエントリのみ削除

        //-----------------------
        // キャッシュクリア予約削除
        Entry::deleteCacheControl($eid);

        AcmsLogger::info('「' . ACMS_RAM::entryTitle(EID) . '」エントリーを非公開にしました');

        //------
        // Hook
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$eid, null]);
        }

        $this->redirect(acmsLink([
            'bid'   => BID,
            'eid'   => $eid,
        ]));
    }
}
