<?php

namespace Acms\Custom;

/**
 * ユーザー定義のHookを設定します。
 */
class Hook
{
    /**
     * 起動時
     * @return void
     */
    public function init()
    {
    }

    /**
     * ログイン判定前
     * @return void
     */
    public function beforeAuthenticate()
    {
    }

    /**
     * ログイン判定後
     * @return void
     */
    public function afterAuthenticate()
    {
    }

    /**
     * 権限チェック
     * @param int|null $suid
     * @param int $bid
     * @return void
     */
    public function restrictionAuthority($suid, $bid)
    {
    }

    /**
     * header指定
     *
     * @param bool $cache キャッシュ利用
     * @return void
     */
    public function header($cache)
    {
        // header('Vary: User-Agent');
        // header('Vary: Accept-Encoding');
        // header('Vary: Accept-Language');
        // header('Vary: Cookie');
    }

    /**
     * クエリ発行前
     *
     * @param string $sql
     * @return void
     */
    public function query(&$sql)
    {
    }

    /**
     * ルール判定のカスタム値
     *
     * @param string $value
     * @return void
     */
    public function customRuleValue(&$value)
    {
        // ここで設定した値を、ルール判定に使用できるようになります。
        // $value = '';
    }

    /**
     * キャッシュルールに特殊ルールを追加
     *
     * @param string $customRuleString
     * @return void
     */
    public function addCacheRule(&$customRuleString)
    {
        // $customRuleString = UA_GROUP; // デバイスによってルールを分ける場合
    }

    /**
     * テンプレートキャッシュ有効時に、
     * インクルードのパスで使用できるグローバル変数を設定
     *
     * ページ毎に値が違うようなグローバル変数を設定しないでください。
     * 値別にキャッシュが作成されるので、値の種類が多いとキャッシュの意味がなくなります。
     *
     * @param string[] $globalVarNames
     * @return void
     */
    public function addGlobalVarsInIncludePath(&$globalVarNames)
    {
        // $globalVarNames = ['SESSION_USER_AUTH', 'HOGE']; // 例）インクルード文に %{SESSION_USER_AUTH} と %{HOGE} を使えるようにする
    }

    /**
     * GETモジュール処理前
     * 解決前テンプレートの中間処理など
     *
     * @param string &$tpl
     * @param \ACMS_GET $thisModule
     * @return void
     */
    public function beforeGetFire(&$tpl, $thisModule)
    {
    }

    /**
     * GETモジュール処理後
     * 解決済みテンプレートの中間処理など
     *
     * @param string &$res
     * @param \ACMS_GET $thisModule
     */
    public function afterGetFire(&$res, $thisModule)
    {
    }

    /**
     * POSTモジュール処理前
     * $thisModuleのプロパティを参照・操作するなど
     *
     * @param \ACMS_POST $thisModule
     * @return void
     */
    public function beforePostFire($thisModule)
    {
    }

    /**
     * POSTモジュール処理後
     * $thisModuleのプロパティを参照・操作するなど
     *
     * @param \ACMS_POST $thisModule
     * @return void
     */
    public function afterPostFire($thisModule)
    {
    }

    /**
     * ビルド前（GETモジュール解決前）
     *
     * @param $tpl &$tpl テンプレート文字列
     * @return void
     */
    public function beforeBuild(&$tpl)
    {
    }

    /**
     * ビルド後（GETモジュール解決後）
     * ※ 空白の除去・文字コードの変換・POSTモジュールに対するSIDの割り当てなどはこの後に行われます
     *
     * @param string &$res レスポンス文字列
     * @return void
     */
    public function afterBuild(&$res)
    {
    }

    /**
     * HTTPレスポンス直前に呼ばれます
     *
     * @param string &$res レスポンス文字列
     * @return void
     */
    public function beforeResponse(&$res)
    {
    }

    /**
     * エントリー作成、更新時 または エントリーインポート時（CSV, WordPress, Movable Type）
     *
     * @param int $eid エントリーID
     * @param int|null $revisionId リビジョンID
     * @return void
     */
    public function saveEntry($eid, $revisionId)
    {
    }

    /**
     * メディア作成・更新時
     *
     * @param int $mid メディアID
     * @param string $method 新規・更新（insert|update|）
     * @param bool $isUpload アップロードファイルがあるかどうか
     * @return void
     */
    public function saveMedia($mid, $method, $isUpload)
    {
        // $data = \Media::getMedia($mid);
    }

    /**
     * フォーム Submit時
     *
     * @param array $mail 自動返信メール
     * @param array $mailAdmin 管理者宛メール
     * @return void
     */
    public function formSubmit($mail, $mailAdmin)
    {
    }

    /**
     * 承認通知
     *
     * @param array $data 通知データ
     * @param bool &$send falseを設定するとデフォルトのメールが飛ばないように設定
     * @return void
     */
    public function approvalNotification($data, &$send = true)
    {
    }

    /**
     * 処理の一番最後のシャットダウン時
     *
     * @return void
     */
    public function beforeShutdown()
    {
    }

    /**
     * グローバル変数の拡張
     *
     * @param \Field $globalVars
     * @return void
     */
    public function extendsGlobalVars(&$globalVars)
    {
        // $globalVars->set('key', 'var');
    }

    /**
     * 引用ユニット拡張
     * $htmlに値を設定すると、その値を引用ユニットのHTMLとして保存します。
     * @param string $url 引用URL
     * @param string &$html 整形後HTML
     * @return void
     */
    public function extendsQuoteUnit($url, &$html)
    {
    }

    /**
     * ビデオユニット拡張
     * $videoIdに値を設定すると、その値をビデオIDとして保存します。
     * @param string $url ユニットに設定されたURL
     * @param string &$videoId 上書き用のVideo ID
     * @return void
     */
    public function extendsVideoUnit($url, &$videoId)
    {
        // $parsed_url = parse_url($url);
        // if (!empty($parsed_url['path'])) {
        //     $id = preg_replace('@/@', '', $parsed_url['path']);
        // }
    }

    /**
     * キャッシュのリフレッシュ時
     * @return void
     */
    public function cacheRefresh()
    {
    }

    /**
     * キャッシュのクリア時
     * @return void
     */
    public function cacheClear()
    {
    }

    /**
     * メディアデータ作成
     * @param string $path 作成先パス
     * @return void
     */
    public function mediaCreate($path)
    {
    }

    /**
     * メディアデータ削除
     * @param string $path 削除パス
     * @return void
     */
    public function mediaDelete($path)
    {
    }


    /**
     * エントリーのフルテキストをカスタマイズ
     *
     * @param array<string, string> &$entry
     * @param array<string, string[]> &$field
     * @param int $entryId
     * @return void
     */
    public function filterEntryFulltext(array &$entry, array &$field, int $entryId): void
    {
        // // エントリーIDとコードをフルテキスト検索から除外する
        // unset($entry['id']);
        // unset($entry['code']);

        // // 特定のフィールドをフルテキスト検索から除外する
        // $exceptFields = [
        //     'private_field',
        //     'internal_memo',
        // ];
        // foreach ($exceptFields as $exceptField) {
        //     unset($field[$exceptField]);
        // }
    }

    /**
     * ユーザーのフルテキストをカスタマイズ
     *
     * @param array<string, string> &$user
     * @param array<string, string[]> &$field
     * @param int $userId
     * @return void
     */
    public function filterUserFulltext(array &$user, array &$field, int $userId): void
    {
        // // メールアドレスをフルテキスト検索から除外する
        // unset($user['mail']);
        // unset($user['mail_mobile']);

        // // 特定のフィールドをフルテキスト検索から除外する
        // $exceptFields = [
        //     'private_field',
        //     'internal_memo',
        // ];
        // foreach ($exceptFields as $exceptField) {
        //     unset($field[$exceptField]);
        // }
    }

    /**
     * カテゴリーのフルテキストをカスタマイズ
     *
     * @param array<string, string> &$category
     * @param array<string, string[]> &$field
     * @param int $categoryId
     * @return void
     */
    public function filterCategoryFulltext(array &$category, array &$field, int $categoryId): void
    {
        // // カテゴリーコードをフルテキスト検索から除外する
        // unset($category['code']);

        // // 特定のフィールドをフルテキスト検索から除外する
        // $exceptFields = [
        //     'internal_note',
        //     'admin_memo',
        // ];
        // foreach ($exceptFields as $exceptField) {
        //     unset($field[$exceptField]);
        // }
    }

    /**
     * ブログのフルテキストをカスタマイズ
     *
     * @param array<string, string> &$blog
     * @param array<string, string[]> &$field
     * @param int $blogId
     * @return void
     */
    public function filterBlogFulltext(array &$blog, array &$field, int $blogId): void
    {
        // // ドメインをフルテキスト検索から除外する
        // unset($blog['domain']);

        // // 特定のフィールドをフルテキスト検索から除外する
        // $exceptFields = [
        //     'private_setting',
        //     'system_config',
        // ];
        // foreach ($exceptFields as $exceptField) {
        //     unset($field[$exceptField]);
        // }
    }

    /**
     * デリアライズ可能なクラスを追加
     *
     * @param string[] &$classes デフォルトで許可されているクラスの配列。必要なクラスをこの配列に追加する。
     * @return void
     */
    public function unserializeAllowedClasses(array &$classes): void
    {
        // $classes[] = \Acms\Plugins\SamplePlugin\SampleClass::class;
    }
}
