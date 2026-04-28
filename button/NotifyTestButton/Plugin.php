<?php

namespace App\Plugins\NotifyTestButton;

use Exceedone\Exment\Services\Plugin\PluginButtonBase;
use Exceedone\Exment\Model\NotifyNavbar;

/**
 * 通知テストボタンプラグイン
 *
 * 一覧画面のメニューボタンから、ログインユーザへの通知
 * テスト通知を1件作成します。
 */
class Plugin extends PluginButtonBase
{
    /**
     * ボタンクリック時の処理
     *
     * @return array<string, mixed>
     */
    public function execute()
    {
        try {
            $userId = \Exment::getUserId();

            if (empty($userId)) {
                return [
                    'result'    => false,
                    'swal'      => '通知テスト',
                    'swaltext'  => 'ログインユーザを取得できませんでした。',
                ];
            }

            $now = now()->format('Y-m-d H:i:s');

            $notify = new NotifyNavbar();
            // notify_id: 紐づく通知マスタID。テスト送信のため -1 (Exment標準もデフォルト -1 を使用)
            $notify->notify_id        = -1;
            $notify->target_user_id   = $userId;        // 通知の宛先 (ログインユーザ自身)
            $notify->trigger_user_id  = $userId;        // 通知の発火元
            $notify->parent_id        = null;
            $notify->parent_type      = null;
            $notify->notify_subject   = '【テスト通知】プラグインからの送信';
            $notify->notify_body      = "プラグイン『通知テストボタン』から送信されました。\n送信時刻: {$now}";
            $notify->read_flg         = 0;
            $notify->save();

            return [
                'result'    => true,
                'swal'      => '通知テスト送信完了',
                'swaltext'  => "通知を確認してください。\n(ID: {$notify->id})",
                'reload'    => true,
            ];
        } catch (\Throwable $e) {
            \Log::error('NotifyTestButton error: ' . $e->getMessage());
            return [
                'result'    => false,
                'swal'      => '通知テスト失敗',
                'swaltext'  => $e->getMessage(),
            ];
        }
    }
}
