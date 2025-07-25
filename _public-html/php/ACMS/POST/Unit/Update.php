<?php

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Webhook;

class ACMS_POST_Unit_Update extends ACMS_POST_Unit
{
    function post()
    {
        $bid = (int) $this->Post->get('bid');
        $eid = (int) $this->Post->get('eid');
        $entry = ACMS_RAM::entry($eid);

        if (!roleEntryUpdateAuthorization($bid, $entry)) {
            die();
        }

        /** @var \Acms\Services\Unit\Repository $unitRepository */
        $unitRepository = Application::make('unit-repository');

        $units = $unitRepository->extractUnits(null);
        $imageUnitIdTable = $unitRepository->saveUnits($units, $eid, $bid, true);

        $primaryImageId_p = $this->Post->get('primary_image');
        $primaryImageId = empty($imageUnitIdTable) ? null : (
            !UTID ? reset($imageUnitIdTable) : (
                !empty($imageUnitIdTable[UTID]) ? $imageUnitIdTable[UTID] : reset($imageUnitIdTable)
            )
        );

        if (intval($primaryImageId) > 0 && intval($primaryImageId_p) === intval($primaryImageId)) {
            $sql = SQL::newUpdate('entry');
            $sql->addUpdate('entry_primary_image', $primaryImageId);
            $sql->addWhereOpr('entry_id', $eid);
            $sql->addWhereOpr('entry_blog_id', $bid);
            Database::query($sql->get(dsn()), 'exec');
            ACMS_RAM::entry($eid, null);
        }

        Common::saveFulltext('eid', $eid, Common::loadEntryFulltext($eid));
        $this->fixEntry($eid);
        // キャッシュクリア
        ACMS_POST_Cache::clearEntryPageCache($eid); // このエントリのみ削除

        //------
        // Hook
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('saveEntry', [$eid, 1]);
            Webhook::call(BID, 'entry', 'entry:updated', [$eid, null]);
        }
        $log = isset($units[0]) ? $units[0]->getLegacyData() : [];

        Logger::info('「' . ACMS_RAM::entryTitle($eid) . '」エントリーのユニットを更新しました', $log);

        return $this->Post;
    }
}
