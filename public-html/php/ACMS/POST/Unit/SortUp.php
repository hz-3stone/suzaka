<?php

class ACMS_POST_Unit_SortUp extends ACMS_POST_Unit
{
    public function post()
    {
        /** @var int|null $utid */
        $utid   = UTID;
        /** @var int|null $eid */
        $eid    = EID;
        if (is_null($eid)) {
            return $this->Post;
        }
        $entry  = ACMS_RAM::entry($eid);
        if (!roleEntryUpdateAuthorization(BID, $entry)) {
            die();
        }
        $DB     = DB::singleton(dsn());

        $SQL    = SQL::newSelect('column');
        $SQL->addSelect('column_sort');
        $SQL->addWhereOpr('column_id', $utid);
        $SQL->addWhereOpr('column_entry_id', $eid);
        $sort   = $DB->query($SQL->get(dsn()), 'one');

        if ($sort == 1) {
            return $this->Post;
        }

        $above  = $sort - 1;

        // previous unit down
        $SQL    = SQL::newUpdate('column');
        $SQL->addUpdate('column_sort', $sort);
        $SQL->addWhereOpr('column_sort', $above);
        $SQL->addWhereOpr('column_entry_id', $eid);
        $DB->query($SQL->get(dsn()), 'exec');

        // current unit up
        $SQL    = SQL::newUpdate('column');
        $SQL->addUpdate('column_sort', $above);
        $SQL->addWhereOpr('column_id', $utid);
        $SQL->addWhereOpr('column_entry_id', $eid);
        $DB->query($SQL->get(dsn()), 'exec');

        $this->fixEntry($eid);
        // キャッシュクリア
        ACMS_POST_Cache::clearEntryPageCache($eid); // このエントリのみ削除

        AcmsLogger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーの「' . $sort . '」番目のユニットを上に移動しました');

        return $this->Post;
    }
}
