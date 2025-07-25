<?php

namespace Acms\Services\Media;

use Rhukster\DomSanitizer\DOMSanitizer;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Image;
use Acms\Services\Facades\Storage;
use Acms\Services\Facades\Common;
use Acms\Services\Unit\Contracts\Model;
use SQL;
use SQL_Select;
use ACMS_RAM;
use ACMS_Hook;
use RuntimeException;

class Helper
{
    /**
     * メディアライブラリの利用が許可されているかどうかを確認
     * @param int $bid
     * @return bool
     */
    public function validate($bid = BID)
    {
        if (!IS_LICENSED) {
            return false;
        }
        if (config('media_library') !== 'on') {
            return false;
        }
        if (roleAvailableUser()) {
            if (!roleAuthorization('media_upload', $bid)) {
                return false;
            }
        } else {
            if (!sessionWithContribution($bid)) {
                return false;
            }
        }
        return true;
    }

    /**
     * メディアの編集が許可されているかどうかを確認
     * @param int $mid
     * @return bool
     */
    public function canEdit($mid)
    {
        if (sessionWithCompilation()) {
            return true;
        }
        if (isSessionContributor()) {
            $sql = SQL::newSelect('media');
            $sql->setSelect('media_user_id');
            $sql->addWhereOpr('media_id', $mid);
            $ownerId = DB::query($sql->get(dsn()), 'one');
            if (intval(SUID) === intval($ownerId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * メディアの基本情報を取得
     * @param array{name: string, tmp_name: string, type: string, size: int} $fileObj
     * @param string $tags
     * @param string $name
     * @return false|array{tags: string, name: string, file: array{name: string, tmp_name: string, type: string, size: int}, size: int, type: string, extension: string}
     */
    public function getBaseInfo($fileObj, $tags, $name)
    {
        if (!preg_match('@\.([^.]+)$@', $fileObj['name'], $match) && $fileObj['name'] !== 'blob') {
            return false;
        }
        $info = getimagesize($fileObj['tmp_name']);
        $extension = Image::detectImageExtenstion($info['mime']);

        return [
            'tags' => $tags,
            'name' => $name,
            'file' => $fileObj,
            'size' => $fileObj['size'],
            'type' => $fileObj['type'],
            'extension' => $extension,
        ];
    }

    /**
     * メディアをコピーして新しい画像ファイルを作成する
     * @param int $mid
     * @param string $filename
     * @return array{
     *   path: string,
     *   name: string,
     *   original: string
     * }
     */
    public function copyImages(int $mid, string $filename = ''): array
    {
        $oldData = $this->getMedia($mid);
        $oldPath = $oldData['path'];
        $filename = $filename ?: $oldData['name'];

        $oldPath = MEDIA_LIBRARY_DIR . $oldPath;
        $info = pathinfo($oldPath);
        $name = preg_replace('/\.[^.]*$/u', '', Storage::mbBasename($filename));
        $name = preg_replace('/\s/u', '_', $name);
        $dir = empty($info['dirname']) ? '' : $info['dirname'] . '/';
        $ext = empty($info['extension']) ? '' : '.' . $info['extension'];

        $newPath = $dir . $name . $ext;
        $newPath = uniqueFilePath($newPath, '');
        $newName = preg_replace("/(.+)(\.[^.]+$)/", "$1", Storage::mbBasename($newPath));
        copyFile($oldPath, $newPath);
        copyFile(otherSizeImagePath($oldPath, 'large'), otherSizeImagePath($newPath, 'large'));
        copyFile(otherSizeImagePath($oldPath, 'tiny'), otherSizeImagePath($newPath, 'tiny'));

        return [
            'path' => substr($newPath, strlen(MEDIA_LIBRARY_DIR)),
            'name' => $newName . $ext,
            'original' => substr($newPath, strlen(MEDIA_LIBRARY_DIR)),
        ];
    }

    /**
     * メディアをコピーして新しいファイルを作成する
     * @param int $mid
     * @param string $filename
     * @return array{
     *   path: string,
     *   name: string
     * }
     */
    public function copyFiles(int $mid, string $filename = ''): array
    {
        $oldData = $this->getMedia($mid);
        $oldPath = $oldData['path'];
        $status = $oldData['status'];
        $baseDir = $status ? MEDIA_STORAGE_DIR : MEDIA_LIBRARY_DIR;
        $filename = $filename ?: $oldData['name'];

        $oldPath = $baseDir . $oldPath;
        $info = pathinfo($oldPath);
        $name = preg_replace('/\.[^.]*$/u', '', Storage::mbBasename($filename));
        $name = preg_replace('/\s/u', '_', $name);
        $dir = empty($info['dirname']) ? '' : $info['dirname'] . '/';
        $ext = empty($info['extension']) ? '' : $info['extension'];

        $newPath = $dir . $name . '.' . $ext;
        $newPath = uniqueFilePath($newPath, '');
        $newName = preg_replace("/(.+)(\.[^.]+$)/", "$1", Storage::mbBasename($newPath));
        copyFile($oldPath, $newPath);

        return [
            'path' => substr($newPath, strlen($baseDir)),
            'name' => $newName . '.' . $ext,
        ];
    }

    /**
     * 画像をアップロード
     * @param string $fieldName
     * @param bool $original
     * @return array{
     *   path: string,
     *   type: string,
     *   name: string,
     *   size: string,
     *   filesize: int<0, max>|false,
     *   extension: string,
     * }
     */
    public function uploadImage($fieldName = 'file', $original = true)
    {
        $size = [
            'normal' => 0,
            'tiny' => 330,
            'square' => -1,
        ];
        if ($original) {
            $size['large'] = 99999;
        }
        $isRamdomFileName = config('media_image_ramdom_filename', 'off') === 'on';
        $forceLarge = isset($size['large']);

        /**
         * @var array{
         *  path: string,
         *  type: string,
         *  name: string,
         *  size: string
         * } $data
         */
        $data = Image::createImages(
            $_FILES[$fieldName],
            $size,
            MEDIA_LIBRARY_DIR,
            $isRamdomFileName,
            null,
            $forceLarge
        );
        $data['extension'] = $data['type'];
        $data['type'] = 'image';
        $data['name'] = Storage::mbBasename($data['path']);
        $data['filesize'] = @filesize(MEDIA_LIBRARY_DIR . $data['path']);

        return $data;
    }

    /**
     * PDFのサムネイルをアップロード
     * @param string $name
     * @return array{
     *   path: string,
     *   type: string,
     *   name: string,
     *   size: string
     * }
     */
    public function uploadPdfThumbnail($name)
    {
        return Image::createImages(
            $_FILES[$name],
            ['normal' => 99999],
            MEDIA_LIBRARY_DIR,
            true,
            null,
            true
        );
    }

    /**
     * SVGをアップロード
     * @param int $size
     * @param string $fieldName
     * @return array{
     *   path: string,
     *   type: string,
     *   name: string,
     *   size: string,
     *   filesize: int,
     * }
     */
    public function uploadSvg($size, $fieldName = 'file')
    {
        $data = $this->createFile(MEDIA_LIBRARY_DIR, $fieldName, false);
        $data['extension'] = $data['type'];
        $data['type'] = 'svg';
        $data['size'] = '';
        $data['filesize'] = $size;

        return $data;
    }

    /**
     * ファイルをアップロード
     * @param int $size
     * @param string $fieldName
     * @return array{
     *   path: string,
     *   type: string,
     *   name: string,
     *   size: string,
     *   filesize: int,
     *   extension: string,
     * }|false
     */
    public function uploadFile($size, $fieldName = 'file')
    {
        Storage::makeDirectory(MEDIA_STORAGE_DIR);

        $data = $this->createFile(MEDIA_STORAGE_DIR, $fieldName, false);
        if (!Storage::exists(MEDIA_STORAGE_DIR . $data['path'])) {
            return false;
        }
        $data['extension'] = $data['type'];
        $data['type'] = 'file';
        $data['size'] = '';
        $data['filesize'] = $size;

        return $data;
    }

    /**
     * 画像ファイルを削除
     *
     * @param int $mid
     * @param bool $removeOriginal
     * @return void
     */
    public function deleteImage($mid, $removeOriginal = true)
    {
        $oldData = $this->getMedia($mid);
        if (!isset($oldData['path'])) {
            return;
        }
        $this->removeImageFiles($oldData['path'], $removeOriginal);
    }

    /**
     * サムネイル画像を削除
     *
     * @param int $mid
     * @return void
     */
    public function deleteThumbnail($mid)
    {
        $oldData = $this->getMedia($mid);
        if (!isset($oldData['thumbnail'])) {
            return;
        }
        $this->removeImageFiles($oldData['path'], true);
    }

    /**
     * ファイルを削除
     * @param int $mid
     * @return void
     */
    public function deleteFile($mid)
    {
        $oldData = $this->getMedia($mid);
        $status = $oldData['status'];
        $path = $oldData['path'];
        $baseDir = $status ? MEDIA_STORAGE_DIR : MEDIA_LIBRARY_DIR;
        Storage::remove($baseDir . $path);
        Image::deleteImageAllSize(MEDIA_LIBRARY_DIR . $oldData['thumbnail']);
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('mediaDelete', $baseDir . $path);
        }
    }

    /**
     * ファイルをリネーム
     * @param array $data
     * @param string $rename
     * @return array
     */
    public function rename($data, $rename)
    {
        if ($data['name'] === $rename) {
            return $data;
        }
        $type = $data['type'];
        $basename = preg_replace("/(.+)(\.[^.]+$)/", "$1", $rename) . '.' . strtolower($data['extension']);

        $path = $data['path'];
        $renamePath = trim(dirname($path), '/') . '/' . $basename;
        if ($type === 'image' || $type === 'svg') {
            $renamePath = uniqueFilePath($renamePath, MEDIA_LIBRARY_DIR); // 名前の重複を避ける
        } elseif ($type === 'file') {
            $renamePath = uniqueFilePath($renamePath, MEDIA_STORAGE_DIR); // 名前の重複を避ける
        }
        $data['name'] = Storage::mbBasename($renamePath);
        $data['path'] = $renamePath;

        if ($type === 'image') {
            $normalPath = $path;
            foreach (['normal', 'large', 'tiny', 'square'] as $imageType) {
                $fromPath = otherSizeImagePath($normalPath, $imageType);
                $toPath = otherSizeImagePath($renamePath, $imageType);
                Storage::move(MEDIA_LIBRARY_DIR . $fromPath, MEDIA_LIBRARY_DIR . $toPath);
                Storage::move(MEDIA_LIBRARY_DIR . $fromPath . '.webp', MEDIA_LIBRARY_DIR . $toPath . '.webp');
            }
            $data['original'] = otherSizeImagePath($renamePath, 'large');

            // mode_xxxxファイルを削除
            $cacheImagePath = trim(dirname(MEDIA_LIBRARY_DIR . $path), '/') . '/*-' . Storage::mbBasename($path);
            $cacheImages = glob($cacheImagePath);
            if (is_array($cacheImages)) {
                foreach ($cacheImages as $filename) {
                    if (preg_match('/(tiny|large|square)-(.*)$/', $filename)) {
                        continue;
                    }
                    Storage::remove($filename);
                    Storage::remove($filename . '.webp');
                }
            }
        } elseif ($type === 'svg') {
            Storage::move(MEDIA_LIBRARY_DIR . $path, MEDIA_LIBRARY_DIR . $renamePath);
            $data['original'] = $renamePath;
        } elseif ($type === 'file') {
            Storage::move(MEDIA_STORAGE_DIR . $path, MEDIA_STORAGE_DIR . $renamePath);
        }
        return $data;
    }

    /**
     * パスをURLエンコード
     * @param string $path
     * @return string
     */
    public function urlencode($path)
    {
        $name = Storage::mbBasename($path);
        return substr($path, 0, strlen($path) - strlen($name)) . rawurlencode($name);
    }

    /**
     * キャッシュバスティング
     * @param string $updated
     * @return string
     */
    public function cacheBusting(string $updated): string
    {
        return '?v=' . date('YmdHis', strtotime($updated));
    }

    /**
     * メディアタイプが画像かどうか
     * @param string $type
     * @return bool
     */
    public function isImageFile($type)
    {
        return preg_match('/^image/', $type) && !preg_match('/svg/', $type);
    }

    /**
     * メディアタイプがSVGかどうか
     * @param string $type
     * @return bool
     */
    public function isSvgFile($type)
    {
        return preg_match('/svg/', $type) === 1;
    }

    /**
     * メディアタイプがファイルかどうか
     * @param string $type
     * @return bool
     */
    public function isFile($type)
    {
        return preg_match('/^file/', $type) === 1;
    }

    /**
     * 編集されたパスを取得
     * @param string $path
     * @return string
     */
    public function getEdited($path)
    {
        return '/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $path;
    }

    /**
     * 画像のサムネイルパスを取得
     * @param string $path
     * @return string
     */
    public function getImageThumbnail($path)
    {
        return '/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . otherSizeImagePath($path, 'tiny');
    }

    /**
     * SVGのサムネイルパスを取得
     * @param string $path
     * @return string
     */
    public function getSvgThumbnail($path)
    {
        return '/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $path;
    }

    /**
     * ファイルのサムネイルパスを取得
     * @param string $extension
     * @return string
     */
    public function getFileThumbnail($extension)
    {
        return '/' . DIR_OFFSET . pathIcon($extension);
    }

    /**
     * PDFのサムネイルパスを取得
     * @param string $path
     * @return string
     */
    public function getPdfThumbnail($path)
    {
        return '/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $path;
    }

    /**
     * 画像のパーマリンクを取得
     * @param string $path
     * @return string
     */
    public function getImagePermalink($path)
    {
        $permalink = BASE_URL . MEDIA_LIBRARY_DIR . $path;
        if (ARCHIVES_CACHE_SERVER) {
            $permalink = ARCHIVES_CACHE_SERVER . '/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $path;
        }
        return $permalink;
    }

    /**
     * ファイルのパーマリンクを取得
     * @param int $mid
     * @param bool $fullpath
     * @return string
     */
    public function getFilePermalink($mid, $fullpath = true)
    {
        if ($fullpath) {
            return acmsLink(['bid' => BID], false) . MEDIA_FILE_SEGMENT . '/' . $mid . '/' . $this->getDownloadLinkHash($mid) . '/' . ACMS_RAM::mediaExtension($mid) . '/';
        }
        $offset = rtrim(DIR_OFFSET . acmsPath(['bid' => BID]), '/');
        if (strlen($offset) > 0) {
            $offset .= '/';
        }
        return '/' . $offset .  MEDIA_FILE_SEGMENT . '/' . $mid . '/' . $this->getDownloadLinkHash($mid) . '/' . ACMS_RAM::mediaExtension($mid) . '/';
    }

    /**
     * ダウンロードリンクハッシュを取得
     * @param int $mid
     * @return string
     */
    public function getDownloadLinkHash($mid)
    {
        $pepper = sha1('pepper' . PASSWORD_SALT_1);
        $hash = sha1($pepper . $mid . PASSWORD_SALT_1);

        return substr($hash, 0, 16);
    }

    /**
     * ファイルの古いパーマリンクを取得
     * @param string $path
     * @param bool $fullpath
     * @return string
     */
    public function getFileOldPermalink($path, $fullpath = true)
    {
        if ($fullpath) {
            return BASE_URL . MEDIA_LIBRARY_DIR . $path;
        }
        return '/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $path;
    }

    /**
     * オリジナルパスを取得
     * @param string $original
     * @return string
     */
    public function getOriginal($original)
    {
        if ($original && !Storage::exists(MEDIA_LIBRARY_DIR . $original)) {
            $original = '';
        }
        if (empty($original)) {
            return '';
        }
        return '/' . DIR_OFFSET . MEDIA_LIBRARY_DIR . $original;
    }

    /**
     * タグをフィルタリング
     * @param SQL_Select $SQL
     * @param string[] $tags
     * @return false|void
     */
    public function filterTag($SQL, $tags)
    {
        if (!is_array($tags) and empty($tags)) {
            return false;
        }

        $tag = array_shift($tags);
        $SQL->addLeftJoin('media_tag', 'media_tag_media_id', 'media_id', 'tag0');
        $SQL->addWhereOpr('media_tag_name', $tag, '=', 'AND', 'tag0');
        $i  = 1;
        while ($tag = array_shift($tags)) {
            $SQL->addLeftJoin('media_tag', 'media_tag_media_id', 'media_tag_media_id', 'tag' . $i, 'tag' . ($i - 1));
            $SQL->addWhereOpr('media_tag_name', $tag, '=', 'AND', 'tag' . $i);
            $i++;
        }
    }

    /**
     * タグを保存
     * @param int $mid
     * @param string $tags
     * @param int $bid
     * @return void
     */
    public function saveTags($mid, $tags, $bid = BID)
    {
        $SQL = SQL::newDelete('media_tag');
        $SQL->addWhereOpr('media_tag_media_id', $mid);
        DB::query($SQL->get(dsn()), 'exec');

        $tags = Common::getTagsFromString($tags);
        foreach ($tags as $sort => $tag) {
            $SQL = SQL::newInsert('media_tag');
            $SQL->addInsert('media_tag_name', $tag);
            $SQL->addInsert('media_tag_sort', $sort + 1);
            $SQL->addInsert('media_tag_media_id', $mid);
            $SQL->addInsert('media_tag_blog_id', $bid);

            DB::query($SQL->get(dsn()), 'exec');
        }
    }

    /**
     * タグを削除
     * @param string $tagName
     * @param int $bid
     * @return void
     */
    public function deleteTag($tagName, $bid = BID)
    {
        $DB = DB::singleton(dsn());
        $SQL = SQL::newDelete('media_tag');
        $SQL->addWhereOpr('media_tag_name', $tagName);
        $SQL->addWhereOpr('media_tag_blog_id', $bid);
        $DB->query($SQL->get(dsn()), 'exec');
    }

    /**
     * タグを更新
     * @param string $oldTag
     * @param string $newTag
     * @param int $bid
     * @return void
     */
    public function updateTag($oldTag, $newTag, $bid = BID)
    {
        $DB = DB::singleton(dsn());
        $SQL    = SQL::newSelect('media_tag');
        $SQL->setSelect('media_tag_media_id');
        $SQL->addWhereIn('media_tag_name', [$oldTag, $newTag]);
        $SQL->addWhereOpr('media_tag_blog_id', BID);
        $SQL->setGroup('media_tag_media_id');
        $SQL->setHaving(SQL::newOpr('media_tag_media_id', 2, '>=', null, 'COUNT'));
        $q  = $SQL->get(dsn());

        if ($DB->query($q, 'fetch') and ($row = $DB->fetch($q))) {
            do {
                $eid    = intval($row['media_tag_media_id']);
                $Del    = SQL::newDelete('media_tag');
                $Del->addWhereOpr('media_tag_name', $newTag);
                $Del->addWhereOpr('media_tag_media_id', $eid);
                $Del->addWhereOpr('media_tag_blog_id', BID);
                $DB->query($Del->get(dsn()), 'exec');
            } while ($row = $DB->fetch($q));
        }

        $SQL    = SQL::newUpdate('media_tag');
        $SQL->setUpdate('media_tag_name', $newTag);
        $SQL->addWhereOpr('media_tag_name', $oldTag);
        $SQL->addWhereOpr('media_tag_blog_id', $bid);
        $DB->query($SQL->get(dsn()), 'exec');
    }

    /**
     * メディアを挿入
     * @param int $mid
     * @param array $data
     * @return void
     */
    public function insertMedia($mid, $data)
    {
        $DB = DB::singleton(dsn());

        $SQL = SQL::newInsert('media');
        $SQL->addInsert('media_id', $mid);
        $SQL->addInsert('media_type', $data['type']);
        $SQL->addInsert('media_extension', $data['extension']);
        $SQL->addInsert('media_path', $data['path']);
        $SQL->addInsert('media_file_name', $data['name']);
        $SQL->addInsert('media_file_size', $data['filesize']);
        $SQL->addInsert('media_image_size', $data['size']);
        if (isset($data['thumbnail'])) {
            $SQL->addInsert('media_thumbnail', $data['thumbnail']);
        }
        if ($data['type'] === 'file') {
            if (isset($data['status']) && $data['status']) {
                $SQL->addInsert('media_status', $data['status']);
            } else {
                $SQL->addInsert('media_status', config('media_default_status', 'entry'));
                $data['status'];
            }
        } else {
            $SQL->addInsert('media_original', otherSizeImagePath($data['path'], 'large'));
        }
        foreach (['1', '2', '3', '4', '5', '6'] as $i) {
            if (isset($data['field_' . $i])) {
                $SQL->addInsert('media_field_' . $i, $data['field_' . $i]);
            }
        }
        $SQL->addInsert('media_upload_date', date('Y-m-d H:i:s', REQUEST_TIME));
        $SQL->addInsert('media_update_date', date('Y-m-d H:i:s', REQUEST_TIME));
        $SQL->addInsert('media_last_update_user_id', SUID);
        $SQL->addInsert('media_user_id', SUID);
        $SQL->addInsert('media_blog_id', BID);

        $DB->query($SQL->get(dsn()), 'exec');
    }

    /**
     * メディアを更新
     * @param int $mid
     * @param array $data
     * @return void
     */
    public function updateMedia($mid, $data)
    {
        $DB = DB::singleton(dsn());
        if (isset($data['original'])) {
            $old = loadMedia($mid);
            if ($old->get('original') !== $data['original']) {
                // オリジナル画像を更新する場合は古いファイルを削除
                Storage::remove($old->get('original'));
            }
        }
        $field = [
            'type' => 'media_type',
            'status' => 'media_status',
            'extension' => 'media_extension',
            'path' => 'media_path',
            'original' => 'media_original',
            'name' => 'media_file_name',
            'filesize' => 'media_file_size',
            'size' => 'media_image_size',
            'update_date' => 'media_update_date',
            'last_update_user_id' => 'media_last_update_user_id',
            'thumbnail' => 'media_thumbnail',
            'field_1' => 'media_field_1',
            'field_2' => 'media_field_2',
            'field_3' => 'media_field_3',
            'field_4' => 'media_field_4',
            'field_5' => 'media_field_5',
            'field_6' => 'media_field_6',
        ];
        $SQL = SQL::newUpdate('media');
        foreach ($field as $key => $column) {
            if (isset($data[$key])) {
                $SQL->addUpdate($column, $data[$key]);
            }
        }
        $SQL->addWhereOpr('media_id', $mid);
        $DB->query($SQL->get(dsn()), 'exec');
    }

    /**
     * JSONをビルド
     * @param int $mid
     * @param array $data
     * @param string $tags
     * @param int $bid
     * @return array{
     *   media_status: string,
     *   media_title: string,
     *   media_label: string,
     *   media_last_modified: string,
     *   media_datetime: string,
     *   media_id: int,
     *   media_bid: int,
     *   media_blog_name: string,
     *   media_user_id: int,
     *   media_user_name: string,
     *   media_last_update_user_id: int|'',
     *   media_last_update_user_name: string,
     *   media_size: string,
     *   media_filesize: int,
     *   media_path: string,
     *   media_edited: string,
     *   media_original: string,
     *   media_thumbnail: string,
     *   media_permalink: string,
     *   media_type: string,
     *   media_ext: string,
     *   media_caption: string,
     *   media_link: string,
     *   media_alt: string,
     *   media_text: string,
     *   media_focal_point: string,
     *   media_editable: bool,
     *   media_pdf_page: string,
     *   checked: false
     * }
     */
    public function buildJson($mid, $data, $tags, $bid = BID)
    {
        $path = $this->urlencode($data['path']);
        $type = $data['type'];
        $extension = $data['extension'];
        $original = '';
        $edited = '';
        if ($type === 'file') {
            if (empty($data['status'])) {
                $permalink = $this->getFileOldPermalink($path);
            } else {
                $permalink = $this->getFilePermalink($mid);
            }
            if (strtolower($extension) === 'pdf' && $data['thumbnail']) {
                $thumbnail = $this->getPdfThumbnail($data['thumbnail']);
            } else {
                $thumbnail = $this->getFileThumbnail($extension);
            }
        } else {
            $edited = $this->getEdited($path);
            $permalink = $this->getImagePermalink($path);
            $original = $this->urlencode($this->getOriginal($data['original']));
            if ($type === 'svg') {
                $thumbnail = $this->getSvgThumbnail($path);
            } else {
                $thumbnail = $this->getImageThumbnail($path);
            }
        }
        return [
            "media_status" => $data['status'],
            "media_title" => $data['name'],
            "media_label" => $tags,
            "media_last_modified" => $data['update_date'],
            "media_datetime" => $data['upload_date'],
            "media_id" => intval($mid),
            "media_bid" => intval($bid),
            "media_blog_name" => isset($data['blog_name']) ? $data['blog_name'] : ACMS_RAM::blogName($bid),
            "media_user_id" => intval($data['user_id']),
            "media_user_name" => $data['user_name'],
            'media_last_update_user_id' => isset($data['last_update_user_id']) ? intval($data['last_update_user_id']) : '',
            'media_last_update_user_name' => isset($data['last_update_user_name']) ? $data['last_update_user_name'] : '',
            "media_size" => $data['size'],
            "media_filesize" => intval($data['filesize']),
            "media_path" => $path,
            "media_edited" => $edited,
            "media_original" => $original,
            "media_thumbnail" => $thumbnail,
            "media_permalink" => $permalink,
            "media_type" => $type,
            "media_ext" => $extension,
            "media_caption" => isset($data['field_1']) ? $data['field_1'] : '',
            "media_link" => isset($data['field_2']) ? $data['field_2'] : '',
            "media_alt" => isset($data['field_3']) ? $data['field_3'] : '',
            "media_text" => isset($data['field_4']) ? $data['field_4'] : '',
            "media_focal_point" => isset($data['field_5']) ? $data['field_5'] : '',
            "media_editable" => isset($data['editable']) ? $data['editable'] : false,
            "media_pdf_page" => isset($data['field_6']) ? $data['field_6'] : '',
            "checked" => false
        ];
    }

    /**
     * メディアのアーカイブリストを取得
     * @param \SQL $sql
     * @return string[]
     */
    public function getMediaArchiveList($sql)
    {
        $archives = [];
        $archive = new SQL_Select($sql);
        $archive->addSelect('media_upload_date');
        $archive->addSelect(SQL::newFunction('media_upload_date', ['SUBSTR', 0, 7]), 'media_date');
        $archive->addGroup('media_date');
        $all = DB::query($archive->get(dsn()), 'all');
        foreach ($all as $row) {
            $archives[] = $row['media_date'];
        }
        return $archives;
    }

    /**
     * メディアのタグリストを取得
     * @param \SQL $sql
     * @return string[]
     */
    public function getMediaTagList($sql)
    {
        $tags = [];
        $tag = new SQL_Select($sql);
        $tag->addLeftJoin('media_tag', 'media_tag_media_id', 'media_id');
        $tag->addGroup('media_tag_name');
        $all = DB::query($tag->get(dsn()), 'all');
        foreach ($all as $row) {
            if ($row['media_tag_name']) {
                $tags[] = $row['media_tag_name'];
            }
        }
        return $tags;
    }

    /**
     * メディアの拡張子リストを取得
     * @param \SQL $sql
     * @return string[]
     */
    public function getMediaExtensionList($sql)
    {
        $exts = [];
        $ext = new SQL_Select($sql);
        $ext->addGroup('media_extension');
        $all = DB::query($ext->get(dsn()), 'all');
        foreach ($all as $row) {
            if ($row['media_extension']) {
                $exts[] = $row['media_extension'];
            }
        }
        return $exts;
    }

    /**
     * メディアのラベルを取得
     * @param int $mid
     * @return string
     */
    public function getMediaLabel($mid)
    {
        $label = '';
        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('media_tag');
        $SQL->addSelect('media_tag_name');
        $SQL->addWhereOpr('media_tag_media_id', $mid);
        $q = $SQL->get(dsn());
        $DB->query($q, 'fetch');
        while ($row = $DB->fetch($q)) {
            if ($label) {
                $label = $label . ',' . $row['media_tag_name'];
            } else {
                $label = $row['media_tag_name'];
            }
        }
        return $label;
    }

    /**
     * Summary of mediaEagerLoadFromUnit
     * @param Model[] $units
     * @return array<int, array<string, mixed>>
     */
    public function mediaEagerLoadFromUnit(array $units): array
    {
        $mediaList = [];
        $mediaDataList = [];
        foreach ($units as $unit) {
            $type = $unit->getUnitType();
            if ($type === 'media') {
                $mediaData = $unit->getField1();
                if (empty($mediaData)) {
                    continue;
                }
                $mediaAry = $unit->explodeUnitData($mediaData);
                foreach ($mediaAry as $i => $mediaId) {
                    $mediaList[] = $mediaId;
                }
            }
        }
        if ($mediaList) {
            $SQL = SQL::newSelect('media');
            $SQL->addWhereIn('media_id', $mediaList);
            $q = $SQL->get(dsn());
            $DB = DB::singleton(dsn());
            $DB->query($q, 'fetch');
            while ($media = $DB->fetch($q)) {
                $mediaId = intval($media['media_id']);
                $mediaDataList[$mediaId] = $media;
            }
        }
        return $mediaDataList;
    }

    /**
     * メディアを取得
     * @param int $mid
     * @return array{
     *   mid: int,
     *   bid: int,
     *   status: string,
     *   path: string,
     *   thumbnail: string,
     *   name: string,
     *   size: string,
     *   filesize: int,
     *   type: string,
     *   extension: string,
     *   original: string,
     *   update_date: string,
     *   upload_date: string,
     *   field_1: string,
     *   field_2: string,
     *   field_3: string,
     *   field_4: string,
     *   field_5: string,
     *   field_6: string,
     *   blog_name: string,
     *   user_id: int,
     *   user_name: string,
     *   last_update_user_id: int,
     *   last_update_user_name: string,
     *   editable: bool
     * }|array{}
     */
    public function getMedia($mid)
    {
        $sql = SQL::newSelect('media', 'm');
        foreach (
            [
                [
                    'field' => 'm.*',
                    'alias' => null,
                    'scope' => null,
                    'function' => null
                ],
                [
                    'field' => 'user_name',
                    'alias' => 'user_name',
                    'scope' => 'user',
                    'function' => null
                ],
                [
                    'field' => 'user_name',
                    'alias' => 'last_update_user_name',
                    'scope' => 'last_update_user',
                    'function' => null
                ],
                [
                    'field' => 'blog_name',
                    'alias' => null,
                    'scope' => null,
                    'function' => null
                ],
            ] as $select
        ) {
            $sql->addSelect(
                $select['field'],
                $select['alias'],
                $select['scope'],
                $select['function']
            );
        }

        $sql->addLeftJoin('blog', 'blog_id', 'media_blog_id');
        $sql->addLeftJoin('user', 'user_id', 'media_user_id', 'user');
        $sql->addLeftJoin('user', 'user_id', 'media_last_update_user_id', 'last_update_user');
        $sql->addWhereOpr('media_id', $mid);
        $row = DB::query($sql->get(dsn()), 'row');
        if (empty($row)) {
            return [];
        }

        return [
            'mid' => $row['media_id'],
            'bid' => $row['media_blog_id'],
            'status' => $row['media_status'],
            'path' => $row['media_path'],
            'thumbnail' => $row['media_thumbnail'],
            'name' => $row['media_file_name'],
            'size' => $row['media_image_size'],
            'filesize' => $row['media_file_size'],
            'type' => $row['media_type'],
            'extension' => $row['media_extension'],
            'original' => $row['media_original'],
            'update_date' => $row['media_update_date'],
            'upload_date' => $row['media_upload_date'],
            'field_1' => $row['media_field_1'],
            'field_2' => $row['media_field_2'],
            'field_3' => $row['media_field_3'],
            'field_4' => $row['media_field_4'],
            'field_5' => $row['media_field_5'],
            'field_6' => $row['media_field_6'],
            'blog_name' => $row['blog_name'],
            'user_id' => $row['media_user_id'],
            'user_name' => $row['user_name'],
            'last_update_user_id' => $row['media_last_update_user_id'],
            'last_update_user_name' => $row['last_update_user_name'],
            'editable' => intval($row['media_user_id']) === SUID
        ];
    }

    /**
     * メディアの削除
     *
     * @param int $mid
     * @return void
     */
    public function deleteItem($mid)
    {
        $DB = DB::singleton(dsn());
        try {
            if (empty($mid) || !self::canEdit($mid)) {
                throw new \RuntimeException('You are not authorized to delete media.');
            }
            $SQL = SQL::newSelect('media');
            $SQL->addWhereOpr('media_id', $mid);
            $q = $SQL->get(dsn());
            $DB->query($q, 'fetch');
            while ($row = $DB->fetch($q)) {
                $type = $row['media_type'];
                if ($type === 'image') {
                    $path = MEDIA_LIBRARY_DIR . $row['media_path'];
                    $original = MEDIA_LIBRARY_DIR . $row['media_original'];
                    Image::deleteImageAllSize($path);
                    Image::deleteImageAllSize($original);
                } else {
                    self::deleteFile($mid);
                }
            }
            $DB = DB::singleton(dsn());
            $SQL = SQL::newDelete('media');
            $SQL->addWhereOpr('media_id', $mid);
            $DB->query($SQL->get(dsn()), 'exec');

            $SQL = SQL::newDelete('media_tag');
            $SQL->addWhereOpr('media_tag_media_id', $mid);
            $DB->query($SQL->get(dsn()), 'exec');
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * メディアフィールドの挿入
     *
     * @param \Field $Field
     * @param array $mediaList
     * @param string[] $useMediaField
     * @return void
     */
    public function injectMediaField($Field, $mediaList, $useMediaField)
    {
        if (empty($mediaList) || empty($useMediaField)) {
            return;
        }
        $useMediaField = array_unique($useMediaField);
        foreach ($useMediaField as $fd) {
            $sourceField = $Field->getArray($fd . '@media');
            $nameAry = [];
            $extensionAry = [];
            $fileSizeAry = [];
            $captionAry = [];
            $linkAry = [];
            $altAry = [];
            $textAry = [];
            $pathAry = [];
            $thumbnailAry = [];
            $pageAry = [];
            $imageSizeAry = [];
            $widthAry = [];
            $heightAry = [];
            $ratioAry = [];
            $focalXAry = [];
            $focalYAry = [];
            $typeAry = [];

            foreach ($sourceField as $i => $mid) {
                if (isset($mediaList[$mid])) {
                    $media = $mediaList[$mid];
                    $path = $this->urlencode($media['media_path']);
                    $type = $media['media_type'];

                    $nameAry[] = $media['media_file_name'];
                    $extensionAry[] = $media['media_extension'];
                    $fileSizeAry[] = $media['media_file_size'];
                    $captionAry[] = $media['media_field_1'];
                    $linkAry[] = $media['media_field_2'];
                    $altAry[] = $media['media_field_3'];
                    $textAry[] = $media['media_field_4'];
                    $pageAry[] = $media['media_field_6'];
                    $typeAry[] = $type;

                    if ($type === 'image') {
                        $path .= $this->cacheBusting($media['media_update_date']);
                        $pathAry[] = $path;
                        $thumbnailAry[] = $this->getImageThumbnail($path);
                        $imageSizeAry[] = $media['media_image_size'];
                        $focalPoint = $media['media_field_5'];
                        $tmpFocalX = '';
                        $tmpFocalY = '';
                        $width = '';
                        $height = '';
                        $ratio = '';

                        if ($media['media_image_size']) {
                            list($w, $h) = explode('x', $media['media_image_size']);
                            $w = intval(trim($w), 10);
                            $h = intval(trim($h), 10);
                            if ($w > 0 && $h > 0) {
                                $width = $w;
                                $height = $h;
                                $ratio = $w / $h;
                            }
                        }
                        if (strpos($focalPoint, ',') !== false) {
                            list($focalX, $focalY) = explode(',', $focalPoint);
                            if ($focalX && $focalY) {
                                $tmpFocalX = ((float)$focalX / 50) - 1;
                                $tmpFocalY = (((float)$focalY / 50) - 1) * -1;
                            }
                        }
                        $focalXAry[] = $tmpFocalX;
                        $focalYAry[] = $tmpFocalY;
                        $widthAry[] = $width;
                        $heightAry[] = $height;
                        $ratioAry[] = $ratio;
                    } elseif ($type === 'svg') {
                        $path .= $this->cacheBusting($media['media_update_date']);
                        $pathAry[] = $path;
                        $thumbnailAry[] = $this->getSvgThumbnail($path);
                        $imageSizeAry[] = '';
                        $focalXAry[] = '';
                        $focalYAry[] = '';
                        $widthAry[] = '';
                        $heightAry[] = '';
                        $ratioAry[] = '';
                    } elseif ($type === 'file') {
                        if (empty($media['media_status'])) {
                            $pathAry[] = $this->getFileOldPermalink($path, false);
                        } else {
                            $pathAry[] = $this->getFilePermalink($mid, false);
                        }
                        if (strtolower($media['media_extension']) === 'pdf' && $media['media_thumbnail']) {
                            $thumbnailAry[] = $this->getPdfThumbnail($media['media_thumbnail']);
                        } else {
                            $thumbnailAry[] = $this->getFileThumbnail($media['media_extension']);
                        }
                        $imageSizeAry[] = '';
                        $focalXAry[] = '';
                        $focalYAry[] = '';
                        $widthAry[] = '';
                        $heightAry[] = '';
                        $ratioAry[] = '';
                    }
                } else {
                    $nameAry[] = '';
                    $extensionAry[] = '';
                    $fileSizeAry[] = '';
                    $captionAry[] = '';
                    $linkAry[] = '';
                    $altAry[] = '';
                    $textAry[] = '';
                    $pathAry[] = '';
                    $thumbnailAry[] = '';
                    $pageAry[] = '';
                    $imageSizeAry[] = '';
                    $focalXAry[] = '';
                    $focalYAry[] = '';
                    $widthAry[] = '';
                    $heightAry[] = '';
                    $ratioAry[] = '';
                    $typeAry[] = '';
                }
            }
            $Field->setField($fd . '@name', $nameAry);
            $Field->setField($fd . '@extension', $extensionAry);
            $Field->setField($fd . '@fileSize', $fileSizeAry);
            $Field->setField($fd . '@caption', $captionAry);
            $Field->setField($fd . '@link', $linkAry);
            $Field->setField($fd . '@alt', $altAry);
            $Field->setField($fd . '@text', $textAry);
            $Field->setField($fd . '@path', $pathAry);
            $Field->setField($fd . '@thumbnail', $thumbnailAry);
            $Field->setField($fd . '@page', $pageAry);
            $Field->setField($fd . '@imageSize', $imageSizeAry);
            $Field->addField($fd . '@focalX', $focalXAry);
            $Field->addField($fd . '@focalY', $focalYAry);
            $Field->addField($fd . '@width', $widthAry);
            $Field->addField($fd . '@height', $heightAry);
            $Field->addField($fd . '@ratio', $ratioAry);
            $Field->addField($fd . '@type', $typeAry);
        }
    }

    /**
     * メディアファイルのダウンロード
     *
     * @param int $mid
     * @return never|void
     */
    public function downloadFile($mid)
    {
        $media = $this->getMedia($mid);
        $download = new Download($media);
        if (!$download->validate()) {
            httpStatusCode('403 Forbidden Media');
        } else {
            $download->download();
        }
    }

    /**
     * SVG（テキスト）をサニタイズ
     *
     * @param string $input
     * @return string
     */
    public function sanitizeSvg(string $input): string
    {
        $sanitizer = new DOMSanitizer(DOMSanitizer::SVG);
        return $sanitizer->sanitize($input);
    }

    /**
     * ファイルをアップロードする
     *
     * @param string $archivesDir
     * @param string $fieldName
     * @param bool $random
     * @return array{path: string, type: string, name: string, size: string}
     * @throws RuntimeException
     */
    protected function createFile(string $archivesDir, string $fieldName = 'file', bool $random = true): array
    {
        if (!isset($_FILES[$fieldName])) {
            throw new RuntimeException('ファイルアップロードに失敗しました');
        }
        $File = $_FILES[$fieldName];
        if (!isset($File['tmp_name'])) {
            throw new RuntimeException('ファイルアップロードに失敗しました');
        }
        if (is_uploaded_file($File['tmp_name']) === false) {
            throw new RuntimeException('ファイルアップロードに失敗しました');
        }
        $src = $File['tmp_name'];
        $fileName = $File['name'];
        if (empty($src)) {
            throw new RuntimeException('ファイルアップロードに失敗しました');
        }
        if (!preg_match('@\.([^.]+)$@', $fileName, $match)) {
            throw new RuntimeException('不正なアップロードを検知しました');
        }
        $nameParts = preg_split('/\./', $fileName);
        array_pop($nameParts);
        $name = implode('.', $nameParts);

        $extension = $match[1];
        $dir = Storage::archivesDir();
        Storage::makeDirectory($archivesDir . $dir);

        if (!$random) {
            $path = $dir . $name . '.' . $extension;
            $path = uniqueFilePath($path, $archivesDir);
            $name = preg_replace("/(.+)(\.[^.]+$)/", "$1", Storage::mbBasename($path));
        } else {
            $path = $dir . uniqueString() . '.' . $extension;
        }
        $file = $archivesDir . $path;

        if (!is_uploaded_file($src)) {
            throw new RuntimeException('不正なアップロードを検知しました');
        }
        if (
            !in_array(
                $extension,
                array_merge(
                    ['svg', 'SVG'],
                    configArray('file_extension_document'),
                    configArray('file_extension_archive'),
                    configArray('file_extension_movie'),
                    configArray('file_extension_audio')
                ),
                true
            )
        ) {
            throw new RuntimeException('許可されていないファイルです');
        }

        $mimeType = Common::getMimeType($src);
        if (preg_match('/svg/', strtolower($mimeType))) {
            // SVGの場合、サニタイズ処理をする
            $dirty = Storage::get($src, dirname($src));
            $clean = $this->sanitizeSvg($dirty);
            Storage::put($file, $clean);
        } else {
            Storage::copy($src, $file);
        }
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('mediaCreate', $file);
        }
        return [
            'path' => $path,
            'type' => strtoupper($extension),
            'name' => $name . '.' . $extension,
            'size' => byteConvert($File['size']),
        ];
    }

    /**
     * 画像パスから、サイズ違い・拡張子違い（webp）など含めて削除
     *
     * @param string $path この値は、ユーザーの入力値など信頼されない値を指定しない
     * @param bool $removeOriginal
     * @return void
     */
    protected function removeImageFiles(string $path, bool $removeOriginal = true): void
    {
        $edited = MEDIA_LIBRARY_DIR . $path;
        $original = MEDIA_LIBRARY_DIR . otherSizeImagePath($path, 'large');
        Storage::remove($edited);
        if (Storage::exists($edited . '.webp')) {
            Storage::remove($edited . '.webp');
        }
        if ($dirname = dirname($edited)) {
            $dirname .= '/';
        }
        $basename = Storage::mbBasename($edited);
        $images = glob($dirname . '*-' . $basename);
        if (is_array($images)) {
            foreach ($images as $filename) {
                if (!$removeOriginal && $filename === $original) {
                    continue;
                }
                Storage::remove($filename);
                if (Storage::exists($filename . '.webp')) {
                    Storage::remove($filename . '.webp');
                }
                if (HOOK_ENABLE) {
                    $Hook = ACMS_Hook::singleton();
                    $Hook->call('mediaDelete', $filename);
                }
            }
        }
    }
}
