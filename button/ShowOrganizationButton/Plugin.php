<?php

namespace App\Plugins\ShowOrganizationButton;

use Exceedone\Exment\Services\Plugin\PluginButtonBase;

class Plugin extends PluginButtonBase
{
    /**
     * ボタンがクリックされた際に呼び出される処理
     * ログインユーザーの所属組織をSweetAlertで表示します。
     *
     * @return array
     */
    public function execute()
    {
        // ログインユーザーを取得
        $user = \Exment::user();

        if (!isset($user)) {
            return [
                'result'   => false,
                'swal'     => 'エラー',
                'swaltext' => 'ログインユーザー情報を取得できませんでした。',
            ];
        }

        // ベースユーザー（CustomValue）を取得
        $baseUser = $user->base_user;

        // ユーザー名を取得
        $userName = $baseUser ? $baseUser->getLabel() : 'ユーザー';

        // 所属組織を取得（belong_organizations は Exment のユーザーCustomValueが持つリレーション）
        $organizations = $baseUser ? ($baseUser->belong_organizations ?? collect()) : collect();

        if ($organizations->isEmpty()) {
            $orgText = '所属している組織はありません。';
        } else {
            // 組織名を改行区切りで結合
            $orgNames = $organizations->map(function ($org) {
                return '・' . $org->getLabel();
            })->toArray();

            $orgText = implode("\n", $orgNames);
        }

        return [
            'result'   => true,
            'swal'     => $userName . ' の所属組織',
            'swaltext' => $orgText,
        ];
    }
}
