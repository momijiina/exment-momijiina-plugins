<?php

namespace App\Plugins\InputDialog;

use Exceedone\Exment\Services\Plugin\PluginButtonBase;
use Exceedone\Exment\Model\CustomTable;

class Plugin extends PluginButtonBase
{
    protected $useCustomOption = true;

    /**
     * プラグイン設定画面: 対象列を選択
     */
    public function setCustomOptionForm(&$form)
    {
        $editableTypes = [
            'text', 'textarea', 'editor', 'url', 'email',
            'integer', 'decimal', 'currency',
            'date', 'time', 'datetime',
            'select', 'select_valtext', 'select_table',
            'yesno', 'boolean',
        ];

        // カスタムオプションで選択されたテーブルからカラム一覧を取得
        $tables = CustomTable::all()->pluck('table_view_name', 'table_name')->toArray();

        $form->select('setting_table', '列選択用テーブル')
            ->options($tables)
            ->help('更新対象列を選択するテーブルを指定してください（「対象テーブル」と同じテーブルを選択）。選択後に保存すると列一覧が表示されます。');

        $settingTable = $this->plugin->getCustomOption('setting_table');
        $allColumns = [];
        if ($settingTable) {
            $table = CustomTable::getEloquent($settingTable);
            if ($table) {
                foreach ($table->custom_columns as $col) {
                    if (in_array($col->column_type, $editableTypes)) {
                        $allColumns[$col->column_name] = $col->column_view_name;
                    }
                }
            }
        }

        $form->multipleSelect('target_columns', '更新対象列')
            ->options($allColumns)
            ->help('ダイアログに表示する列を選択してください。未選択の場合は全ての編集可能列が表示されます。');

        // ボタン表示条件
        $form->exmheader('ボタン表示条件')->hr();

        if (!empty($allColumns)) {
            $form->select('condition_column', '条件カラム')
                ->options(array_merge(['' => '（条件なし・常に表示）'], $allColumns))
                ->help('ボタンを表示する条件に使うカラムを選択してください。');

            $form->select('condition_operator', '条件演算子')
                ->options([
                    'eq'       => '等しい（=）',
                    'ne'       => '等しくない（≠）',
                    'empty'    => '空である',
                    'not_empty' => '空でない',
                    'gt'       => 'より大きい（>）',
                    'gte'      => '以上（>=）',
                    'lt'       => 'より小さい（<）',
                    'lte'      => '以下（<=）',
                    'contains' => '含む',
                ])
                ->default('eq')
                ->help('条件の比較方法を選択してください。');

            $form->text('condition_value', '条件値')
                ->help('比較する値を入力してください（「空である」「空でない」の場合は不要）。');
        }
    }

    /**
     * ボタン表示条件チェック
     */
    public function enableRender()
    {
        $condColumn = $this->plugin->getCustomOption('condition_column');
        if (empty($condColumn)) {
            return true;
        }

        $operator = $this->plugin->getCustomOption('condition_operator', 'eq');
        $condValue = $this->plugin->getCustomOption('condition_value', '');
        $actual = $this->custom_value->getValue($condColumn);

        // select_table等でオブジェクトの場合
        if (is_object($actual)) {
            if (method_exists($actual, 'getKey')) {
                $actual = $actual->getKey();
            } elseif (property_exists($actual, 'id')) {
                $actual = $actual->id;
            } else {
                $actual = (string)$actual;
            }
        }

        switch ($operator) {
            case 'eq':
                return (string)$actual === (string)$condValue;
            case 'ne':
                return (string)$actual !== (string)$condValue;
            case 'empty':
                return empty($actual) && $actual !== '0' && $actual !== 0;
            case 'not_empty':
                return !empty($actual) || $actual === '0' || $actual === 0;
            case 'gt':
                return floatval($actual) > floatval($condValue);
            case 'gte':
                return floatval($actual) >= floatval($condValue);
            case 'lt':
                return floatval($actual) < floatval($condValue);
            case 'lte':
                return floatval($actual) <= floatval($condValue);
            case 'contains':
                return strpos((string)$actual, (string)$condValue) !== false;
            default:
                return true;
        }
    }

    /**
     * execute
     */
    public function execute()
    {
        return [
            'result' => true,
        ];
    }

    /**
     * カスタムボタン + モーダルダイアログをレンダリング
     */
    public function render()
    {
        $columns = $this->custom_table->custom_columns;
        $columnData = [];

        // 設定画面で選択された列を取得
        $selectedColumns = $this->plugin->getCustomOption('target_columns');
        if (is_string($selectedColumns)) {
            $selectedColumns = array_filter(explode(',', $selectedColumns));
        }
        if (!is_array($selectedColumns)) {
            $selectedColumns = [];
        }

        // 編集可能なカラムタイプ
        $editableTypes = [
            'text', 'textarea', 'editor', 'url', 'email',
            'integer', 'decimal', 'currency',
            'date', 'time', 'datetime',
            'select', 'select_valtext', 'select_table',
            'yesno', 'boolean',
        ];

        foreach ($columns as $column) {
            if (!in_array($column->column_type, $editableTypes)) {
                continue;
            }

            // 選択列が設定されている場合、その列のみ表示
            if (!empty($selectedColumns) && !in_array($column->column_name, $selectedColumns)) {
                continue;
            }

            $currentValue = $this->custom_value->getValue($column->column_name);

            // select_table の場合、オブジェクトからIDを取得
            if (is_object($currentValue)) {
                if (method_exists($currentValue, 'getKey')) {
                    $currentValue = $currentValue->getKey();
                } elseif (property_exists($currentValue, 'id')) {
                    $currentValue = $currentValue->id;
                } else {
                    $currentValue = (string)$currentValue;
                }
            }
            // 配列の場合（マルチセレクト等）
            if (is_array($currentValue)) {
                $currentValue = implode(',', $currentValue);
            }

            $col = [
                'column_name'      => $column->column_name,
                'column_view_name' => $column->column_view_name,
                'column_type'      => $column->column_type,
                'current_value'    => $currentValue,
            ];

            // セレクト系オプション取得
            if (in_array($column->column_type, ['select', 'select_valtext'])) {
                $col['select_options'] = $this->getSelectOptions($column);
            }

            // select_table のオプション取得
            if ($column->column_type === 'select_table') {
                $col['select_options'] = $this->getSelectTableOptions($column);
            }

            $columnData[] = $col;
        }

        $tableName     = $this->custom_table->table_name;
        $tableViewName = $this->custom_table->table_view_name;
        $recordId      = $this->custom_value->id;
        $token         = csrf_token();
        $updateUrl     = admin_url("data/{$tableName}/{$recordId}");
        $detailUrl     = admin_url("data/{$tableName}/{$recordId}");
        $columnJson    = json_encode($columnData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $modalId       = 'inputDialogModal_' . $recordId;

        $html = $this->generateHtml($tableViewName, $updateUrl, $detailUrl, $token, $columnJson, $modalId);

        // Exment は render() の戻り値に render() メソッドを持つオブジェクトを期待する
        return new class($html) implements \Illuminate\Contracts\Support\Renderable {
            private $html;
            public function __construct(string $html) { $this->html = $html; }
            public function render() { return $this->html; }
            public function __toString() { return $this->html; }
        };
    }

    /**
     * select / select_valtext のオプションを取得
     */
    private function getSelectOptions($column)
    {
        $selectItems = array_get($column->options, 'select_item', '');
        if (!is_string($selectItems) || empty(trim($selectItems))) {
            return [];
        }

        $items = array_filter(explode("\n", str_replace("\r", "", $selectItems)), function ($v) {
            return trim($v) !== '';
        });

        if ($column->column_type === 'select_valtext') {
            return array_values(array_map(function ($item) {
                $parts = explode(',', $item, 2);
                return [
                    'value' => trim($parts[0]),
                    'text'  => isset($parts[1]) ? trim($parts[1]) : trim($parts[0]),
                ];
            }, $items));
        }

        return array_values(array_map(function ($item) {
            $v = trim($item);
            return ['value' => $v, 'text' => $v];
        }, $items));
    }

    /**
     * select_table のオプションを関連テーブルから取得
     */
    private function getSelectTableOptions($column)
    {
        $targetTableId = array_get($column->options, 'select_target_table');
        if (!$targetTableId) {
            return [];
        }

        $targetTable = CustomTable::getEloquent($targetTableId);
        if (!$targetTable) {
            return [];
        }

        $records = $targetTable->getValueModel()->take(200)->get();
        return $records->map(function ($record) {
            return [
                'value' => $record->id,
                'text'  => $record->getLabel(),
            ];
        })->toArray();
    }

    /**
     * ボタン + モーダル + JavaScript の HTML を生成
     */
    private function generateHtml($tableViewName, $updateUrl, $detailUrl, $token, $columnJson, $modalId)
    {
        $escapedViewName = e($tableViewName);

        return '
<!-- 更新ダイアログ ボタン -->
<button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#' . $modalId . '">
    <i class="fa fa-edit"></i>&nbsp;更新
</button>

<!-- モーダルダイアログ -->
<div class="modal fade" id="' . $modalId . '" tabindex="-1" role="dialog" aria-labelledby="' . $modalId . '_label">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="' . $modalId . '_label">更新</h4>
            </div>
            <div class="modal-body">
                <p>' . $escapedViewName . 'のデータを更新する値を記入してください。</p>
                <div id="' . $modalId . '_fields"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">閉じる</button>
                <button type="button" class="btn btn-info" id="' . $modalId . '_submit">
                    <i class="fa fa-save"></i>&nbsp;送信
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ダイアログ用 JavaScript -->
<script type="application/json" id="' . $modalId . '_data">' . $columnJson . '</script>
<script>
(function() {
    var modalId   = ' . json_encode($modalId) . ';
    var updateUrl = ' . json_encode($updateUrl) . ';
    var detailUrl = ' . json_encode($detailUrl) . ';
    var token     = ' . json_encode($token) . ';

    var dataEl = document.getElementById(modalId + "_data");
    if (!dataEl) return;
    var columns;
    try { columns = JSON.parse(dataEl.textContent); } catch(e) { return; }

    // ---- HTML エスケープ ----
    function esc(str) {
        if (str === null || str === undefined) return "";
        return String(str).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/\x27/g,"&#039;");
    }

    // ---- フォームフィールド組み立て ----
    var html = "";
    for (var i = 0; i < columns.length; i++) {
        var col = columns[i];
        var val = (col.current_value !== null && col.current_value !== undefined) ? col.current_value : "";
        var name = "value[" + col.column_name + "]";
        var field = "";

        switch (col.column_type) {
            case "text":
            case "url":
            case "email":
                var itype = col.column_type === "url" ? "url" : col.column_type === "email" ? "email" : "text";
                field = \'<div class="input-group" style="width:100%">\' +
                    \'<span class="input-group-addon"><i class="fa fa-pencil"></i></span>\' +
                    \'<input type="\' + itype + \'" class="form-control" name="\' + esc(name) + \'" value="\' + esc(val) + \'">\' +
                    \'</div>\';
                break;

            case "textarea":
            case "editor":
                field = \'<textarea class="form-control" name="\' + esc(name) + \'" rows="3">\' + esc(val) + \'</textarea>\';
                break;

            case "integer":
                field = \'<div class="input-group" style="width:100%">\' +
                    \'<span class="input-group-addon"><i class="fa fa-pencil"></i></span>\' +
                    \'<input type="number" step="1" class="form-control" name="\' + esc(name) + \'" value="\' + esc(val) + \'">\' +
                    \'</div>\';
                break;

            case "decimal":
            case "currency":
                field = \'<div class="input-group" style="width:100%">\' +
                    \'<span class="input-group-addon"><i class="fa fa-pencil"></i></span>\' +
                    \'<input type="number" step="0.01" class="form-control" name="\' + esc(name) + \'" value="\' + esc(val) + \'">\' +
                    \'</div>\';
                break;

            case "date":
                field = \'<div class="input-group" style="width:100%">\' +
                    \'<span class="input-group-addon"><i class="fa fa-calendar"></i></span>\' +
                    \'<input type="date" class="form-control" name="\' + esc(name) + \'" value="\' + esc(val) + \'">\' +
                    \'</div>\';
                break;

            case "time":
                field = \'<div class="input-group" style="width:100%">\' +
                    \'<span class="input-group-addon"><i class="fa fa-clock-o"></i></span>\' +
                    \'<input type="time" class="form-control" name="\' + esc(name) + \'" value="\' + esc(val) + \'">\' +
                    \'</div>\';
                break;

            case "datetime":
                var dtVal = val ? String(val).replace(" ", "T").substring(0, 16) : "";
                field = \'<div class="input-group" style="width:100%">\' +
                    \'<span class="input-group-addon"><i class="fa fa-calendar"></i></span>\' +
                    \'<input type="datetime-local" class="form-control" name="\' + esc(name) + \'" value="\' + esc(dtVal) + \'">\' +
                    \'</div>\';
                break;

            case "select":
            case "select_valtext":
            case "select_table":
                var opts = \'<option value="">--</option>\';
                if (col.select_options) {
                    for (var j = 0; j < col.select_options.length; j++) {
                        var o = col.select_options[j];
                        var sel = (String(o.value) === String(val)) ? " selected" : "";
                        opts += \'<option value="\' + esc(o.value) + \'"\' + sel + \'>\' + esc(o.text) + \'</option>\';
                    }
                }
                field = \'<select class="form-control" name="\' + esc(name) + \'">\' + opts + \'</select>\';
                break;

            case "yesno":
            case "boolean":
                var yesS = (val == 1 || val === true || val === "1") ? " selected" : "";
                var noS  = (!val || val == 0 || val === "0") ? " selected" : "";
                field = \'<select class="form-control" name="\' + esc(name) + \'">\' +
                    \'<option value="0"\' + noS + \'>NO</option>\' +
                    \'<option value="1"\' + yesS + \'>YES</option>\' +
                    \'</select>\';
                break;

            default:
                field = \'<input type="text" class="form-control" name="\' + esc(name) + \'" value="\' + esc(val) + \'">\';
        }

        html += \'<div class="form-group" style="margin-bottom:15px;">\' +
            \'<label style="font-weight:bold;">\' + esc(col.column_view_name) + \'</label>\' +
            field +
            \'</div>\';
    }

    if (columns.length === 0) {
        html = \'<p class="text-muted">編集可能な列がありません。</p>\';
    }

    var fieldsEl = document.getElementById(modalId + "_fields");
    if (fieldsEl) fieldsEl.innerHTML = html;

    // ---- 送信処理 ----
    var submitBtn = document.getElementById(modalId + "_submit");
    if (!submitBtn) return;

    submitBtn.addEventListener("click", function() {
        var btn = this;
        var originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = \'<i class="fa fa-spinner fa-spin"></i>&nbsp;送信中...\';

        // フォームデータ収集
        var formFields = document.querySelectorAll("#" + modalId + "_fields input, #" + modalId + "_fields textarea, #" + modalId + "_fields select");
        var postData = "_token=" + encodeURIComponent(token) + "&_method=PUT";
        for (var k = 0; k < formFields.length; k++) {
            var f = formFields[k];
            postData += "&" + encodeURIComponent(f.name) + "=" + encodeURIComponent(f.value);
        }

        // AJAX 送信
        $.ajax({
            url: updateUrl,
            type: "POST",
            data: postData,
            dataType: "text",
            success: function(data, textStatus, xhr) {
                if (typeof toastr !== "undefined") {
                    toastr.success("データを更新しました。");
                } else {
                    alert("データを更新しました。");
                }
                $("#" + modalId).modal("hide");
                setTimeout(function() { location.reload(); }, 600);
            },
            error: function(xhr) {
                var msg = "エラーが発生しました。";
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.errors) {
                        var msgs = [];
                        for (var key in resp.errors) {
                            if (resp.errors.hasOwnProperty(key)) {
                                var arr = resp.errors[key];
                                if (Array.isArray(arr)) {
                                    msgs = msgs.concat(arr);
                                } else {
                                    msgs.push(String(arr));
                                }
                            }
                        }
                        if (msgs.length > 0) msg = msgs.join("\\n");
                    } else if (resp.message) {
                        msg = resp.message;
                    }
                } catch(e) {}

                if (typeof toastr !== "undefined") {
                    toastr.error(msg);
                } else {
                    alert(msg);
                }
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    });
})();
</script>';
    }
}
