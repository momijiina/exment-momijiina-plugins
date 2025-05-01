<?php
namespace App\Plugins\AvatarDownload;

use Exceedone\Exment\Services\Plugin\PluginButtonBase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;


class Plugin extends PluginButtonBase
{
    /**
     * Plugin Button
     */
    public function execute()
    {        
        // 現在ログイン中のユーザーを取得
        $user = Auth::user();

        if (!$user || !$user->avatar) {
             // ファイルが存在しない場合、デフォルト画像を使用
            $base_path = base_path('public/vendor/exment/images/user.png');
        } else {
            // ユーザーのアバター画像のパス
            $base_path = storage_path('app/admin/' . $user->avatar);

             // ファイルが存在しない場合、デフォルト画像を使用
            if (!File::exists($base_path)) {
                $base_path = base_path('public/vendor/exment/images/user.png');
            }
        }


        // ファイル名を設定する
        $fileName = basename($base_path);

        return [
            'fileBase64' => base64_encode(File::get($base_path)),
            'fileContentType' => File::mimeType($base_path),
            'fileName' => $fileName,

            // 任意：「ダウンロードが完了しました」メッセージを表示する
            'swaltext' => 'ダウンロードが完了しました',
        ];
    }
}
