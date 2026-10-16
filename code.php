<?php
// ============================================================
//  index.php  —  PyBlockly
//  Python like building blocks.
//  作者：baibian
//  开发：Gemini & DeepSeek 双模型协作
// ============================================================
//  验证方式：邮箱验证码（QQ邮箱 SMTP 发送）
//  使用前必须修改下方的 SMTP 配置
// ============================================================

ob_start();
session_start();

/* ============================================================
   SMTP 配置（★ 使用前必须修改 ★）
   ------------------------------------------------------------
   $SMTP_USER : 你的完整 QQ 邮箱地址，如 123456@qq.com
   $SMTP_PASS : QQ邮箱生成的 16 位 SMTP 授权码（不是QQ密码）
                获取：QQ邮箱网页版 → 设置 → 账户
                     → 开启 IMAP/SMTP 服务 → 生成授权码
   ============================================================ */
$SMTP_HOST = 'smtp.qq.com';
$SMTP_PORT = 465;
$SMTP_USER = 'your_qq@qq.com';       // ★ 改成你的QQ邮箱
$SMTP_PASS = 'your_smtp_password';   // ★ 改成你的16位授权码
$SMTP_FROM = 'your_qq@qq.com';       // ★ 改成你的QQ邮箱
$SMTP_NAME = 'PyBlockly';

/* ============ 简易 SMTP 发信函数（QQ邮箱 465 SSL） ============ */
function smtp_send($host, $port, $user, $pass, $from, $fromName, $to, $subject, $body) {
    $fp = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 15);
    if (!$fp) return "连接失败: {$errstr} ({$errno})";

    stream_set_timeout($fp, 15);
    $read = function () use ($fp) {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $send = function ($cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };

    $read();
    $send("EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $read();
    $send("AUTH LOGIN");
    $read();
    $send(base64_encode($user));
    $read();
    $send(base64_encode($pass));
    $authResp = $read();
    if (strpos($authResp, '235') === false) { fclose($fp); return "认证失败: " . trim($authResp); }

    $send("MAIL FROM: <{$from}>");
    $read();
    $send("RCPT TO: <{$to}>");
    $read();
    $send("DATA");
    $read();

    $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "\r\n";
    $headers .= chunk_split(base64_encode($body));

    fwrite($fp, $headers . "\r\n.\r\n");
    $dataResp = $read();
    $send("QUIT");
    fclose($fp);

    return (strpos($dataResp, '250') !== false) ? true : "发送失败: " . trim($dataResp);
}

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$action = $_POST['action'] ?? '';

/* ============ 退出授权 ============ */
if ($isAjax && $action === 'logout') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    unset($_SESSION['is_starred']);
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    echo json_encode(['status' => 'success']);
    exit;
}

/* ============ 发送邮箱验证码 ============ */
if ($isAjax && $action === 'send_code') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => '邮箱格式不正确']);
        exit;
    }

    if (isset($_SESSION['email_code_time']) && time() - $_SESSION['email_code_time'] < 60) {
        $left = 60 - (time() - $_SESSION['email_code_time']);
        echo json_encode(['status' => 'error', 'message' => "请 {$left} 秒后再试"]);
        exit;
    }

    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['email_code']      = $code;
    $_SESSION['email_code_time'] = time();
    $_SESSION['email_code_addr'] = $email;

    $subject = "PyBlockly 邮箱验证码";
    $body    = "您的验证码是：{$code}\n\n5 分钟内有效，请勿泄露给他人。\n\n—— PyBlockly 作者 baibian";

    $result = smtp_send($SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $SMTP_FROM, $SMTP_NAME, $email, $subject, $body);

    if ($result === true) {
        echo json_encode(['status' => 'success', 'message' => '验证码已发送，请查收邮箱']);
    } else {
        echo json_encode(['status' => 'error', 'message' => '邮件发送失败：' . $result]);
    }
    exit;
}

/* ============ 校验邮箱验证码 ============ */
if ($isAjax && $action === 'verify_code') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    $email = trim($_POST['email'] ?? '');
    $code  = trim($_POST['code'] ?? '');

    if (!isset($_SESSION['email_code'], $_SESSION['email_code_time'], $_SESSION['email_code_addr'])) {
        echo json_encode(['status' => 'error', 'message' => '请先获取验证码']);
        exit;
    }
    if (time() - $_SESSION['email_code_time'] > 300) {
        echo json_encode(['status' => 'error', 'message' => '验证码已过期，请重新获取']);
        exit;
    }
    if (strcasecmp($email, $_SESSION['email_code_addr']) !== 0) {
        echo json_encode(['status' => 'error', 'message' => '邮箱与发送时不一致']);
        exit;
    }
    if ($code !== $_SESSION['email_code']) {
        echo json_encode(['status' => 'error', 'message' => '验证码错误']);
        exit;
    }

    $_SESSION['is_starred'] = true;
    unset($_SESSION['email_code'], $_SESSION['email_code_time'], $_SESSION['email_code_addr']);
    echo json_encode(['status' => 'success']);
    exit;
}

ob_end_flush();
$isStarred = isset($_SESSION['is_starred']) && $_SESSION['is_starred'] === true;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PyBlockly — 让 Python 像搭积木一样简单</title>
    <meta name="author" content="baibian">
    <meta name="description" content="PyBlockly — Python like building blocks. 基于 Blockly + Pyodide 的可视化 Python 积木编辑器。">

    <script src="https://unpkg.com/blockly@10.4.3/blockly.min.js"></script>
    <script src="https://unpkg.com/blockly@10.4.3/python_compressed.js"></script>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body, html { width: 100%; height: 100%; overflow: hidden; background: #ffffff; font-family: monospace, sans-serif; color: #000000; }

        #star-modal {
            position: fixed; inset: 0; background: rgba(255, 255, 255, 0.95);
            display: <?php echo $isStarred ? 'none' : 'flex'; ?>; justify-content: center; align-items: center; z-index: 9999;
        }
        .modal-card { width: 420px; background: #fff; border: 2px solid #000; padding: 32px; box-shadow: 8px 8px 0 #000; }
        .modal-title { font-size: 16px; font-weight: bold; margin-bottom: 12px; text-transform: uppercase; }
        .modal-desc { font-size: 13px; line-height: 1.5; margin-bottom: 20px; color: #333; }
        .form-input {
            width: 100%; background: #fff; border: 2px solid #000; border-radius: 0;
            padding: 12px; color: #000; font-size: 13px; outline: none; margin-bottom: 16px; font-family: monospace;
        }
        .btn-square {
            width: 100%; background: #000; color: #fff; font-weight: bold; border: 2px solid #000; border-radius: 0;
            padding: 12px; font-size: 13px; cursor: pointer; text-transform: uppercase; font-family: monospace;
            transition: background .1s, color .1s;
        }
        .btn-square:hover { background: #fff; color: #000; }
        .btn-square:disabled { opacity: .5; cursor: not-allowed; }

        header {
            height: 50px; display: flex; justify-content: space-between; align-items: center; padding: 0 20px;
            background: #fff; border-bottom: 2px solid #000; z-index: 10;
        }
        .brand { font-size: 15px; font-weight: bold; letter-spacing: 1px; }
        .brand small { font-size: 11px; font-weight: normal; letter-spacing: 0; color: #666; margin-left: 10px; }
        .header-right { display: flex; align-items: center; gap: 14px; }
        .author { font-size: 12px; font-weight: bold; letter-spacing: 1px; }
        .logout-btn {
            background: #fff; border: 2px solid #000; color: #000;
            padding: 5px 12px; font-family: monospace; font-size: 11px; font-weight: bold;
            cursor: pointer; border-radius: 0; text-transform: uppercase;
            transition: background .1s, color .1s;
        }
        .logout-btn:hover { background: #000; color: #fff; }

        .workspace { display: flex; width: 100%; height: calc(100vh - 50px); }
        #blocklyDiv { flex: 1 1 0%; height: 100%; position: relative; overflow: hidden; background: #ffffff !important; }

        .code-panel {
            width: 460px; height: 100%; background: #fff; border-left: 2px solid #000;
            display: flex; flex-direction: column;
        }
        .code-toolbar {
            height: 40px; padding: 0 16px; display: flex; justify-content: space-between; align-items: center;
            border-bottom: 2px solid #000; font-size: 12px; font-weight: bold; text-transform: uppercase;
            flex-shrink: 0;
        }
        .code-actions { display: flex; gap: 8px; }
        .action-btn {
            background: #fff; border: 2px solid #000; color: #000;
            padding: 4px 10px; border-radius: 0; font-size: 11px; font-weight: bold; cursor: pointer; font-family: monospace;
            transition: background .1s, color .1s;
        }
        .action-btn:hover { background: #000; color: #fff; }
        .action-btn:disabled { opacity: .45; cursor: wait; }
        .action-btn.run { background: #000; color: #fff; }
        .action-btn.run:hover { background: #fff; color: #000; }

        #codeDiv {
            flex: 1 1 auto; min-height: 100px; padding: 16px; font-family: monospace;
            font-size: 12px; color: #000; overflow-y: auto; white-space: pre-wrap; line-height: 1.5;
            background: #fafafa;
        }

        .console-panel { flex: 0 0 230px; min-height: 130px; display: flex; flex-direction: column;
            border-top: 2px solid #000; background: #fff; }
        .console-toolbar {
            height: 32px; padding: 0 16px; display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px dashed #000; font-size: 11px; font-weight: bold; text-transform: uppercase;
            flex-shrink: 0;
        }
        #consoleDiv {
            flex: 1 1 auto; min-height: 0; padding: 10px 16px; font-family: monospace; font-size: 12px;
            line-height: 1.55; white-space: pre-wrap; overflow-y: auto; background: #fff; color: #000;
        }

        .blocklyToolboxDiv {
            width: 122px !important;
            background: #ffffff !important;
            border-right: 2px solid #000000 !important;
            color: #000000 !important;
            font-family: monospace !important;
        }
        .blocklyTreeRow {
            height: 30px !important;
            line-height: 30px !important;
            padding-right: 6px !important;
            padding-left: 6px !important;
            margin: 0 !important;
            border-bottom: 1px dashed #d5d5d5 !important;
            font-size: 12px !important;
        }
        .blocklyTreeRow:hover { background: #f0f0f0 !important; }
        .blocklyTreeSelected { background: #000000 !important; color: #ffffff !important; }
        .blocklyTreeLabel { font-family: monospace !important; font-size: 12px !important; }
        .blocklyTreeIcon { display: none !important; }

        .blocklyMenu, .goog-menu {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0 !important;
            box-shadow: 5px 5px 0 #000000 !important;
            padding: 4px 0 !important;
            font-family: monospace !important;
            font-size: 12px !important;
            color: #000000 !important;
            z-index: 100000 !important;
        }
        .blocklyMenuItem, .goog-menuitem {
            padding: 7px 18px !important;
            color: #000000 !important;
            font-family: monospace !important;
            font-size: 12px !important;
            cursor: pointer !important;
            border-radius: 0 !important;
        }
        .blocklyMenuItemHighlight, .goog-menuitem-highlight {
            background: #000000 !important;
            color: #ffffff !important;
        }
        .blocklyMenuItemDisabled, .goog-menuitem-disabled { opacity: .45 !important; }
        .blocklyMenuSeparator, .goog-menuseparator {
            border-top: 1px dashed #000000 !important;
            margin: 4px 8px !important;
        }
        .blocklyMenuItemContent { font-family: monospace !important; }

        .blocklyZoom, .blocklyTrash {
            pointer-events: auto !important;
            opacity: 1 !important;
            z-index: 20 !important;
            cursor: pointer !important;
        }
        .blocklyZoom > image, .blocklyTrash > image {
            opacity: 0.85 !important;
            cursor: pointer !important;
        }
        .blocklyZoom > image:hover, .blocklyTrash > image:hover { opacity: 1 !important; }
        .blocklyZoomReset { cursor: pointer !important; }

        .blocklyScrollbarHandle { fill: #000000 !important; opacity: .55 !important; }
        .blocklyScrollbarHandle:hover { opacity: .85 !important; }

        .blocklyTooltipDiv {
            background: #ffffff !important; border: 2px solid #000000 !important; border-radius: 0 !important;
            box-shadow: 4px 4px 0 #000000 !important; color: #000000 !important;
            font-family: monospace !important; font-size: 12px !important; padding: 6px 10px !important;
        }

        .blocklyHtmlInput {
            font-family: monospace !important; font-size: 12px !important;
            border: 2px solid #000000 !important; border-radius: 0 !important;
        }
    </style>
</head>
<body>

    <!-- 邮箱验证弹窗 -->
    <div id="star-modal">
        <div class="modal-card">
            <div class="modal-title">邮箱验证</div>
            <div class="modal-desc">
                请输入邮箱获取验证码，验证通过后即可使用编辑器。
            </div>
            <input type="email" id="email-input" class="form-input" placeholder="请输入邮箱地址">
            <button onclick="sendCode()" id="send-btn" class="btn-square" style="margin-bottom:10px;">发送验证码</button>
            <input type="text" id="code-input" class="form-input" placeholder="请输入 6 位验证码" maxlength="6">
            <button onclick="verifyCode()" id="verify-btn" class="btn-square">验证并进入</button>
            <div id="error-msg" style="color:#ff0000;font-size:11px;margin-top:10px;min-height:16px;font-weight:bold;"></div>
        </div>
    </div>

    <header>
        <div class="brand">让 Python 像搭积木一样简单 <small>PyBlockly — Python like building blocks.</small></div>
        <div class="header-right">
            <span class="author">作者：baibian</span>
            <?php if ($isStarred): ?>
            <button class="logout-btn" onclick="logoutAuth()">退出授权</button>
            <?php endif; ?>
        </div>
    </header>

    <div class="workspace">
        <div id="blocklyDiv"></div>

        <div class="code-panel">
            <div class="code-toolbar">
                <span>Python 代码输出</span>
                <div class="code-actions">
                    <button class="action-btn run" id="run-btn" onclick="runPython()">运行</button>
                    <button class="action-btn" onclick="copyCode()">复制</button>
                    <button class="action-btn" onclick="downloadCode()">下载</button>
                </div>
            </div>
            <pre id="codeDiv"># 请在左侧拖拽积木生成 Python 代码...</pre>

            <div class="console-panel">
                <div class="console-toolbar">
                    <span>运行控制台</span>
                    <button class="action-btn" onclick="clearConsole()">清空</button>
                </div>
                <pre id="consoleDiv"># 点击「运行」即可在浏览器中执行 Python 代码</pre>
            </div>
        </div>
    </div>

    <!-- ============ 满满当当的 Python 积木工具箱 ============ -->
    <xml id="toolbox" style="display: none">
        <category name="逻辑" colour="#000000">
            <block type="controls_if"></block>
            <block type="logic_compare"></block>
            <block type="logic_operation"></block>
            <block type="logic_negate"></block>
            <block type="logic_boolean"></block>
            <block type="logic_null"></block>
            <block type="logic_ternary"></block>
        </category>
        <category name="循环" colour="#222222">
            <block type="controls_repeat_ext">
                <value name="TIMES"><shadow type="math_number"><field name="NUM">10</field></shadow></value>
            </block>
            <block type="controls_whileUntil"></block>
            <block type="controls_for">
                <value name="FROM"><shadow type="math_number"><field name="NUM">1</field></shadow></value>
                <value name="TO"><shadow type="math_number"><field name="NUM">10</field></shadow></value>
                <value name="BY"><shadow type="math_number"><field name="NUM">1</field></shadow></value>
            </block>
            <block type="controls_forEach"></block>
            <block type="controls_flow_statements"></block>
        </category>
        <category name="数学" colour="#333333">
            <block type="math_number"><field name="NUM">0</field></block>
            <block type="math_arithmetic"></block>
            <block type="math_single"></block>
            <block type="math_trig"></block>
            <block type="math_constant"></block>
            <block type="math_number_property">
                <value name="NUMBER_TO_CHECK"><shadow type="math_number"><field name="NUM">0</field></shadow></value>
            </block>
            <block type="math_round">
                <value name="NUM"><shadow type="math_number"><field name="NUM">3.1</field></shadow></value>
            </block>
            <block type="math_on_list"></block>
            <block type="math_modulo"></block>
            <block type="math_constrain">
                <value name="VALUE"><shadow type="math_number"><field name="NUM">50</field></shadow></value>
                <value name="LOW"><shadow type="math_number"><field name="NUM">1</field></shadow></value>
                <value name="HIGH"><shadow type="math_number"><field name="NUM">100</field></shadow></value>
            </block>
            <block type="math_random_int">
                <value name="FROM"><shadow type="math_number"><field name="NUM">1</field></shadow></value>
                <value name="TO"><shadow type="math_number"><field name="NUM">100</field></shadow></value>
            </block>
            <block type="math_random_float"></block>
            <block type="math_atan2">
                <value name="X"><shadow type="math_number"><field name="NUM">1</field></shadow></value>
                <value name="Y"><shadow type="math_number"><field name="NUM">1</field></shadow></value>
            </block>
        </category>
        <category name="文本" colour="#555555">
            <block type="text"></block>
            <block type="text_join"></block>
            <block type="text_append">
                <value name="TEXT"><shadow type="text"><field name="TEXT"></field></shadow></value>
            </block>
            <block type="text_length">
                <value name="VALUE"><shadow type="text"><field name="TEXT">abc</field></shadow></value>
            </block>
            <block type="text_isEmpty">
                <value name="VALUE"><shadow type="text"><field name="TEXT"></field></shadow></value>
            </block>
            <block type="text_indexOf">
                <value name="VALUE"><block type="variables_get"><field name="VAR">text</field></block></value>
                <value name="FIND"><shadow type="text"><field name="TEXT">abc</field></shadow></value>
            </block>
            <block type="text_charAt">
                <value name="VALUE"><block type="variables_get"><field name="VAR">text</field></block></value>
            </block>
            <block type="text_getSubstring">
                <value name="STRING"><block type="variables_get"><field name="VAR">text</field></block></value>
            </block>
            <block type="text_changeCase">
                <value name="TEXT"><shadow type="text"><field name="TEXT">abc</field></shadow></value>
            </block>
            <block type="text_trim">
                <value name="TEXT"><shadow type="text"><field name="TEXT"> abc </field></shadow></value>
            </block>
            <block type="text_print">
                <value name="TEXT"><shadow type="text"><field name="TEXT">Hello World</field></shadow></value>
            </block>
            <block type="text_prompt_ext">
                <value name="TEXT"><shadow type="text"><field name="TEXT">请输入内容</field></shadow></value>
            </block>
        </category>
        <category name="列表" colour="#666666">
            <block type="lists_create_empty"></block>
            <block type="lists_create_with"></block>
            <block type="lists_repeat">
                <value name="NUM"><shadow type="math_number"><field name="NUM">5</field></shadow></value>
            </block>
            <block type="lists_length">
                <value name="VALUE"><block type="variables_get"><field name="VAR">list</field></block></value>
            </block>
            <block type="lists_isEmpty">
                <value name="VALUE"><block type="variables_get"><field name="VAR">list</field></block></value>
            </block>
            <block type="lists_indexOf">
                <value name="VALUE"><block type="variables_get"><field name="VAR">list</field></block></value>
            </block>
            <block type="lists_getIndex">
                <value name="VALUE"><block type="variables_get"><field name="VAR">list</field></block></value>
            </block>
            <block type="lists_setIndex">
                <value name="LIST"><block type="variables_get"><field name="VAR">list</field></block></value>
            </block>
            <block type="lists_getSublist">
                <value name="LIST"><block type="variables_get"><field name="VAR">list</field></block></value>
            </block>
            <block type="lists_sort">
                <value name="LIST"><block type="variables_get"><field name="VAR">list</field></block></value>
            </block>
            <block type="lists_split">
                <value name="DELIM"><shadow type="text"><field name="TEXT">,</field></shadow></value>
            </block>
            <block type="lists_reverse">
                <value name="LIST"><block type="variables_get"><field name="VAR">list</field></block></value>
            </block>
        </category>
        <category name="颜色" colour="#888888">
            <block type="colour_picker"></block>
            <block type="colour_random"></block>
            <block type="colour_rgb">
                <value name="RED"><shadow type="math_number"><field name="NUM">100</field></shadow></value>
                <value name="GREEN"><shadow type="math_number"><field name="NUM">50</field></shadow></value>
                <value name="BLUE"><shadow type="math_number"><field name="NUM">0</field></shadow></value>
            </block>
            <block type="colour_blend">
                <value name="COLOUR1"><shadow type="colour_picker"><field name="COLOUR">#ff0000</field></shadow></value>
                <value name="COLOUR2"><shadow type="colour_picker"><field name="COLOUR">#3333ff</field></shadow></value>
                <value name="RATIO"><shadow type="math_number"><field name="NUM">0.5</field></shadow></value>
            </block>
        </category>
        <sep></sep>
        <category name="变量" colour="#111111" custom="VARIABLE"></category>
        <category name="函数" colour="#444444" custom="PROCEDURE"></category>
    </xml>

    <!-- ============ 中文语言包（必须在 inject 之前执行）============ -->
    <script>
    (function () {
        if (!window.Blockly) return;
        if (!Blockly.Msg) Blockly.Msg = {};
        var M = Blockly.Msg;

        M.ADD_COMMENT = '添加注释';
        M.REMOVE_COMMENT = '删除注释';
        M.DUPLICATE_BLOCK = '复制积木';
        M.DUPLICATE_COMMENT = '复制注释';
        M.HELP = '帮助';
        M.COLLAPSE_ALL = '折叠所有积木';
        M.EXPAND_ALL = '展开所有积木';
        M.COLLAPSE_BLOCK = '折叠积木';
        M.EXPAND_BLOCK = '展开积木';
        M.DELETE_BLOCK = '删除积木';
        M.DELETE_X_BLOCKS = '删除 %1 个积木';
        M.DELETE_ALL_BLOCKS = '删除全部 %1 个积木？';
        M.CLEAN_UP = '整理积木';
        M.UNDO = '撤销';
        M.REDO = '重做';
        M.DISABLE_BLOCK = '禁用积木';
        M.ENABLE_BLOCK = '启用积木';
        M.CHANGE_VALUE_TITLE = '修改值';
        M.EXTERNAL_INPUTS = '外部输入';
        M.INLINE_INPUTS = '内联输入';
        M.ZOOM_IN = '放大';
        M.ZOOM_OUT = '缩小';
        M.ZOOM_RESET = '重置缩放';
        M.BLOCK_DELETED = '已删除积木。';
        M.BLOCKS_DELETED = '已删除 %1 个积木。';

        M.RENAME_VARIABLE = '重命名变量...';
        M.DELETE_VARIABLE = '删除变量 "%1"';
        M.DELETE_VARIABLE_CONFIRM = '删除 %2 处对变量 "%1" 的引用？';
        M.RENAME_VARIABLE_TITLE = '将变量 "%1" 重命名为：';
        M.NEW_VARIABLE = '新建变量...';
        M.NEW_VARIABLE_TITLE = '新变量名：';
        M.VARIABLES_GET_TITLE = '读取';
        M.VARIABLES_GET_ITEM = '变量';
        M.VARIABLES_SET = '将 %1 设为 %2';
        M.VARIABLES_SET_TITLE = '赋值';

        M.PROCEDURES_DEFNORETURN_TITLE = '定义';
        M.PROCEDURES_DEFNORETURN_PROCEDURE = '函数名';
        M.PROCEDURES_DEFNORETURN_DO = '执行';
        M.PROCEDURES_DEFNORETURN_TOOLTIP = '创建一个没有返回值的函数。';
        M.PROCEDURES_CALLNORETURN_TOOLTIP = '调用一个无返回值的函数。';
        M.PROCEDURES_DEFRETURN_TITLE = '定义';
        M.PROCEDURES_DEFRETURN_PROCEDURE = '函数名';
        M.PROCEDURES_DEFRETURN_DO = '执行';
        M.PROCEDURES_DEFRETURN_RETURN = '返回';
        M.PROCEDURES_DEFRETURN_TOOLTIP = '创建一个带返回值的函数。';
        M.PROCEDURES_CALLRETURN_TOOLTIP = '调用一个带返回值的函数。';
        M.PROCEDURES_IFRETURN_TOOLTIP = '若条件为真，则返回该值。';
        M.PROCEDURES_HIGHLIGHT_DEF = '高亮函数定义';
        M.PROCEDURES_MUTATORCONTAINER_TITLE = '参数';
        M.PROCEDURES_MUTATORARG_TITLE = '参数名';
        M.PROCEDURES_BEFORE_PARAMS = '参数：';
        M.PROCEDURES_CALL_BEFORE_PARAMS = '参数：';
        M.PROCEDURES_CREATE_DO = '创建 "%1" 调用';

        M.CONTROLS_IF_MSG_IF = '如果';
        M.CONTROLS_IF_MSG_THEN = '那么';
        M.CONTROLS_IF_MSG_ELSE = '否则';
        M.CONTROLS_IF_MSG_ELSEIF = '否则如果';
        M.CONTROLS_IF_IF_TITLE_IF = '如果';
        M.CONTROLS_IF_ELSEIF_TITLE_ELSEIF = '否则如果';
        M.CONTROLS_IF_ELSE_TITLE_ELSE = '否则';
        M.CONTROLS_IF_TOOLTIP_1 = '若条件为真则执行。';
        M.CONTROLS_IF_TOOLTIP_2 = '若条件为真执行第一块，否则执行第二块。';
        M.CONTROLS_IF_TOOLTIP_3 = '若第一条件为真执行第一块；否则若第二条件为真执行第二块。';
        M.CONTROLS_IF_TOOLTIP_4 = '多条件分支执行。';

        M.CONTROLS_REPEAT_TITLE = '重复 %1 次';
        M.CONTROLS_REPEAT_INPUT_DO = '执行';
        M.CONTROLS_REPEAT_TOOLTIP = '重复执行若干次。';
        M.CONTROLS_WHILEUNTIL_OPERATOR_WHILE = '当条件为真时重复';
        M.CONTROLS_WHILEUNTIL_OPERATOR_UNTIL = '重复直到条件为真';
        M.CONTROLS_WHILEUNTIL_TOOLTIP_WHILE = '条件为真时重复执行。';
        M.CONTROLS_WHILEUNTIL_TOOLTIP_UNTIL = '条件为假时重复执行。';
        M.CONTROLS_FOR_TITLE = '用 %1 从 %2 到 %3 步长 %4';
        M.CONTROLS_FOR_TOOLTIP = '让变量从起始数到结束数按步长递增，重复执行。';
        M.CONTROLS_FOR_INPUT_DO = '执行';
        M.CONTROLS_FOREACH_TITLE = '遍历列表 %1 中的每一项 %2';
        M.CONTROLS_FOREACH_INPUT_DO = '执行';
        M.CONTROLS_FOREACH_TOOLTIP = '遍历列表中的每一项。';
        M.CONTROLS_FLOW_STATEMENTS_OPERATOR_BREAK = '跳出循环';
        M.CONTROLS_FLOW_STATEMENTS_OPERATOR_CONTINUE = '继续下一次迭代';
        M.CONTROLS_FLOW_STATEMENTS_TOOLTIP_BREAK = '跳出当前循环。';
        M.CONTROLS_FLOW_STATEMENTS_TOOLTIP_CONTINUE = '跳过本次迭代，进入下一次。';
        M.CONTROLS_FLOW_STATEMENTS_WARNING = '警告：此积木只能在循环中使用。';

        M.LOGIC_COMPARE_TOOLTIP_EQ = '两值相等则返回真。';
        M.LOGIC_COMPARE_TOOLTIP_NEQ = '两值不等则返回真。';
        M.LOGIC_COMPARE_TOOLTIP_LT = '左值小于右值则返回真。';
        M.LOGIC_COMPARE_TOOLTIP_LTE = '左值小于等于右值则返回真。';
        M.LOGIC_COMPARE_TOOLTIP_GT = '左值大于右值则返回真。';
        M.LOGIC_COMPARE_TOOLTIP_GTE = '左值大于等于右值则返回真。';
        M.LOGIC_OPERATION_AND = '且';
        M.LOGIC_OPERATION_OR = '或';
        M.LOGIC_NEGATE_TITLE = '非 %1';
        M.LOGIC_BOOLEAN_TRUE = '真';
        M.LOGIC_BOOLEAN_FALSE = '假';
        M.LOGIC_NULL = '空';
        M.LOGIC_TERNARY_CONDITION = '条件';
        M.LOGIC_TERNARY_IF_TRUE = '若为真';
        M.LOGIC_TERNARY_IF_FALSE = '若为假';

        M.MATH_ARITHMETIC_TOOLTIP_ADD = '返回两数之和。';
        M.MATH_ARITHMETIC_TOOLTIP_MINUS = '返回两数之差。';
        M.MATH_ARITHMETIC_TOOLTIP_MULTIPLY = '返回两数之积。';
        M.MATH_ARITHMETIC_TOOLTIP_DIVIDE = '返回两数之商。';
        M.MATH_ARITHMETIC_TOOLTIP_POWER = '返回第一数的第二数次幂。';
        M.MATH_SINGLE_TOOLTIP_ROOT = '返回该数的平方根。';
        M.MATH_SINGLE_TOOLTIP_ABS = '返回该数的绝对值。';
        M.MATH_SINGLE_TOOLTIP_NEG = '返回该数的相反数。';
        M.MATH_SINGLE_TOOLTIP_LN = '返回该数的自然对数。';
        M.MATH_SINGLE_TOOLTIP_LOG10 = '返回该数的以 10 为底的对数。';
        M.MATH_SINGLE_TOOLTIP_EXP = '返回 e 的该数次幂。';
        M.MATH_SINGLE_TOOLTIP_POW10 = '返回 10 的该数次幂。';
        M.MATH_TRIG_TOOLTIP_SIN = '返回该角度的正弦值。';
        M.MATH_TRIG_TOOLTIP_COS = '返回该角度的余弦值。';
        M.MATH_TRIG_TOOLTIP_TAN = '返回该角度的正切值。';
        M.MATH_TRIG_TOOLTIP_ASIN = '返回该值的反正弦角。';
        M.MATH_TRIG_TOOLTIP_ACOS = '返回该值的反余弦角。';
        M.MATH_TRIG_TOOLTIP_ATAN = '返回该值的反正切角。';
        M.MATH_IS_DIVISIBLE_BY = '能否被整除';
        M.MATH_IS_EVEN = '是偶数';
        M.MATH_IS_ODD = '是奇数';
        M.MATH_IS_PRIME = '是质数';
        M.MATH_IS_WHOLE = '是整数';
        M.MATH_IS_POSITIVE = '是正数';
        M.MATH_IS_NEGATIVE = '是负数';
        M.MATH_IS_TOOLTIP = '判断数字是否为偶数、奇数、质数、整数、正数或负数，返回真或假。';
        M.MATH_ROUND_OPERATOR_ROUND = '四舍五入';
        M.MATH_ROUND_OPERATOR_ROUNDUP = '向上取整';
        M.MATH_ROUND_OPERATOR_ROUNDDOWN = '向下取整';
        M.MATH_ROUND_TOOLTIP = '数字四舍五入 / 向上 / 向下取整。';
        M.MATH_ONLIST_OPERATOR_SUM = '求和';
        M.MATH_ONLIST_OPERATOR_MIN = '最小值';
        M.MATH_ONLIST_OPERATOR_MAX = '最大值';
        M.MATH_ONLIST_OPERATOR_AVERAGE = '平均值';
        M.MATH_ONLIST_OPERATOR_MEDIAN = '中位数';
        M.MATH_ONLIST_OPERATOR_MODE = '众数';
        M.MATH_ONLIST_OPERATOR_STD_DEV = '标准差';
        M.MATH_ONLIST_OPERATOR_RANDOM = '随机一项';
        M.MATH_MODULO_TITLE = '%1 除以 %2 的余数';
        M.MATH_CONSTRAIN_TITLE = '将 %1 限制在 %2 到 %3 之间';
        M.MATH_RANDOM_INT_TITLE = '从 %1 到 %2 之间的随机整数';
        M.MATH_RANDOM_FLOAT_TITLE_RANDOM = '随机小数';
        M.MATH_ATAN2_TITLE = '点 (%1, %2) 的反正切';

        M.TEXT_JOIN_TITLE_CREATEWITH = '拼接文本';
        M.TEXT_CREATE_JOIN_TITLE_JOIN = '连接';
        M.TEXT_APPEND_TITLE = '向 %1 追加文本 %2';
        M.TEXT_APPEND_VARIABLE = '变量';
        M.TEXT_LENGTH_TITLE = '%1 的长度';
        M.TEXT_ISEMPTY_TITLE = '%1 为空？';
        M.TEXT_INDEXOF_TITLE = '在文本 %1 中查找 %2 的 %3';
        M.TEXT_INDEXOF_OPERATOR_FIRST = '首次出现';
        M.TEXT_INDEXOF_OPERATOR_LAST = '最后出现';
        M.TEXT_CHARAT_TITLE = '在文本 %1 中取第 %2 个字符';
        M.TEXT_CHARAT_FROM_START = '取第 # 个字符';
        M.TEXT_CHARAT_FROM_END = '从末尾取第 # 个字符';
        M.TEXT_CHARAT_FIRST = '取第一个字符';
        M.TEXT_CHARAT_LAST = '取最后一个字符';
        M.TEXT_CHARAT_RANDOM = '取随机字符';
        M.TEXT_GET_SUBSTRING_INPUT_IN_TEXT = '在文本';
        M.TEXT_GET_SUBSTRING_START_FROM_START = '从第 # 个字符开始';
        M.TEXT_GET_SUBSTRING_START_FIRST = '从第一个字符开始';
        M.TEXT_GET_SUBSTRING_START_FROM_END = '从末尾第 # 个字符开始';
        M.TEXT_GET_SUBSTRING_END_FROM_START = '到第 # 个字符';
        M.TEXT_GET_SUBSTRING_END_LAST = '到最后一个字符';
        M.TEXT_GET_SUBSTRING_END_FROM_END = '到末尾第 # 个字符';
        M.TEXT_CHANGECASE_TITLE = '将文本 %1 转为 %2';
        M.TEXT_CHANGECASE_OPERATOR_UPPERCASE = '大写';
        M.TEXT_CHANGECASE_OPERATOR_LOWERCASE = '小写';
        M.TEXT_CHANGECASE_OPERATOR_TITLECASE = '首字母大写';
        M.TEXT_TRIM_TITLE = '去除 %1 两端空格';
        M.TEXT_PRINT_TITLE = '打印 %1';
        M.TEXT_PROMPT_TYPE_TEXT = '弹出输入框（文本）';
        M.TEXT_PROMPT_TYPE_NUMBER = '弹出输入框（数字）';

        M.LISTS_CREATE_EMPTY_TITLE = '创建空列表';
        M.LISTS_CREATE_WITH_INPUT_WITH = '创建列表，包含';
        M.LISTS_CREATE_WITH_CONTAINER_TITLE_ADD = '列表';
        M.LISTS_REPEAT_TITLE = '创建列表，重复 %1 共 %2 次';
        M.LISTS_LENGTH_TITLE = '%1 的长度';
        M.LISTS_ISEMPTY_TITLE = '%1 为空？';
        M.LISTS_INDEXOF_FIRST = '首次出现';
        M.LISTS_INDEXOF_LAST = '最后出现';
        M.LISTS_GET_INDEX_GET = '取';
        M.LISTS_GET_INDEX_GET_REMOVE = '取出并删除';
        M.LISTS_GET_INDEX_REMOVE = '删除';
        M.LISTS_GET_INDEX_FROM_START = '第 # 项';
        M.LISTS_GET_INDEX_FROM_END = '从末尾第 # 项';
        M.LISTS_GET_INDEX_FIRST = '第一项';
        M.LISTS_GET_INDEX_LAST = '最后一项';
        M.LISTS_GET_INDEX_RANDOM = '随机一项';
        M.LISTS_SET_INDEX_SET = '设置';
        M.LISTS_SET_INDEX_INSERT = '插入到';
        M.LISTS_SET_INDEX_INPUT_TO = '为';
        M.LISTS_GET_SUBLIST_START_FROM_START = '从第 # 项';
        M.LISTS_GET_SUBLIST_START_FIRST = '从第一项';
        M.LISTS_GET_SUBLIST_START_FROM_END = '从末尾第 # 项';
        M.LISTS_GET_SUBLIST_END_FROM_START = '到第 # 项';
        M.LISTS_GET_SUBLIST_END_LAST = '到最后一项';
        M.LISTS_GET_SUBLIST_END_FROM_END = '到末尾第 # 项';
        M.LISTS_SORT_TITLE = '%1 排序 %2 第 %3 项';
        M.LISTS_SORT_ORDER_ASCENDING = '升序';
        M.LISTS_SORT_ORDER_DESCENDING = '降序';
        M.LISTS_SORT_TYPE_NUMERIC = '按数字';
        M.LISTS_SORT_TYPE_TEXT = '按字母';
        M.LISTS_SORT_TYPE_IGNORECASE = '按字母（忽略大小写）';
        M.LISTS_SPLIT_LIST_FROM_TEXT = '将文本转换为列表';
        M.LISTS_SPLIT_TEXT_FROM_LIST = '将列表转换为文本';
        M.LISTS_SPLIT_WITH_DELIMITER = '使用分隔符';
        M.LISTS_REVERSE_MESSAGE0 = '反转 %1';

        M.COLOUR_PICKER_TOOLTIP = '从调色板中选取颜色。';
        M.COLOUR_RANDOM_TITLE = '随机颜色';
        M.COLOUR_RANDOM_TOOLTIP = '随机生成一个颜色。';
        M.COLOUR_RGB_TITLE = '颜色';
        M.COLOUR_RGB_RED = '红';
        M.COLOUR_RGB_GREEN = '绿';
        M.COLOUR_RGB_BLUE = '蓝';
        M.COLOUR_BLEND_TITLE = '混合';
        M.COLOUR_BLEND_COLOUR1 = '颜色 1';
        M.COLOUR_BLEND_COLOUR2 = '颜色 2';
        M.COLOUR_BLEND_RATIO = '比例';
    })();
    </script>

    <!-- ============ 初始化工作区 & 交互脚本 ============ -->
    <script>
        var demoWorkspace = Blockly.inject('blocklyDiv', {
            toolbox: document.getElementById('toolbox'),
            grid: { spacing: 20, length: 2, snap: true, colour: '#e5e5e5' },
            zoom: { controls: true, wheel: true, startScale: 1.0, maxScale: 2, minScale: 0.5 },
            trashcan: true,
            theme: Blockly.Themes.Classic,
            renderer: 'zelos'
        });

        var PLACEHOLDER = "# 请在左侧拖拽积木生成 Python 代码...";

        function updateCode() {
            try {
                var code = Blockly.Python.workspaceToCode(demoWorkspace);
                document.getElementById('codeDiv').innerText = code || PLACEHOLDER;
            } catch (e) { console.error(e); }
        }
        demoWorkspace.addChangeListener(updateCode);

        function copyCode() {
            var codeText = document.getElementById('codeDiv').innerText;
            navigator.clipboard.writeText(codeText).then(function () {
                alert('代码已复制到剪贴板。');
            }).catch(function () {
                alert('复制失败，请手动选中复制。');
            });
        }

        function downloadCode() {
            var codeText = document.getElementById('codeDiv').innerText;
            var blob = new Blob([codeText], { type: 'text/plain;charset=utf-8' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url; a.download = 'main.py';
            a.click(); URL.revokeObjectURL(url);
        }

        var consoleStarted = false;

        function consoleLog(msg) {
            var el = document.getElementById('consoleDiv');
            if (!consoleStarted) { el.textContent = ''; consoleStarted = true; }
            el.textContent += (el.textContent ? '\n' : '') + msg;
            el.scrollTop = el.scrollHeight;
        }
        function consoleErr(msg) { consoleLog(msg); }
        function clearConsole() {
            document.getElementById('consoleDiv').textContent = '# 控制台已清空';
            consoleStarted = true;
        }

        var PYODIDE_BASE = 'https://cdn.jsdelivr.net/pyodide/v0.26.2/full/';
        var pyodideInstance = null;
        var pyodideLoading = null;

        function loadPyodideScript() {
            if (window.loadPyodide) return Promise.resolve();
            return new Promise(function (resolve, reject) {
                var s = document.createElement('script');
                s.src = PYODIDE_BASE + 'pyodide.js';
                s.onload = resolve;
                s.onerror = function () { reject(new Error('Pyodide 脚本加载失败，请检查网络。')); };
                document.head.appendChild(s);
            });
        }
        function getPyodide() {
            if (pyodideInstance) return Promise.resolve(pyodideInstance);
            if (!pyodideLoading) {
                pyodideLoading = loadPyodideScript()
                    .then(function () { return loadPyodide({ indexURL: PYODIDE_BASE }); })
                    .then(function (py) { pyodideInstance = py; return py; });
            }
            return pyodideLoading;
        }

        async function runPython() {
            var btn = document.getElementById('run-btn');
            var code = document.getElementById('codeDiv').innerText;

            if (!code.trim() || code.trim() === PLACEHOLDER.trim() || demoWorkspace.getAllBlocks(false).length === 0) {
                consoleLog('# ⚠️ 没有可运行的代码，请先在左侧拖拽积木。');
                return;
            }

            btn.disabled = true;
            var oldText = btn.textContent;
            btn.textContent = '运行中';

            try {
                if (!pyodideInstance) consoleLog('# 首次运行：正在加载 Python 运行环境（约 10MB），请稍候…');
                var py = await getPyodide();

                py.setStdout({ batched: function (t) { consoleLog(t); } });
                py.setStderr({ batched: function (t) { consoleErr(t); } });
                try {
                    py.setStdin({
                        stdin: function () {
                            var v = window.prompt('程序请求输入：');
                            return (v === null || v === undefined) ? '' : v;
                        }
                    });
                } catch (e) { }

                consoleLog('# ─── 开始运行 ───');
                await py.runPythonAsync(code);
                consoleLog('# ─── 运行结束 ───');
            } catch (err) {
                consoleErr('# ❌ 错误：' + (err && err.message ? err.message : String(err)));
            } finally {
                btn.disabled = false;
                btn.textContent = oldText;
            }
        }

        function sendCode() {
            var email = document.getElementById('email-input').value.trim();
            var errorMsg = document.getElementById('error-msg');
            var btn = document.getElementById('send-btn');

            if (!email) { errorMsg.innerText = "请先填写邮箱。"; return; }

            errorMsg.innerText = "";
            btn.disabled = true;
            var oldText = btn.innerText;
            btn.innerText = "发送中...";

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    btn.disabled = false;
                    btn.innerText = oldText;
                    if (xhr.status === 200) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res.status === 'success') {
                                errorMsg.style.color = '#000';
                                errorMsg.innerText = res.message;
                            } else {
                                errorMsg.style.color = '#ff0000';
                                errorMsg.innerText = res.message;
                            }
                        } catch (e) { errorMsg.innerText = "解析响应失败"; }
                    } else { errorMsg.innerText = "网络请求失败"; }
                }
            };
            xhr.send('action=send_code&email=' + encodeURIComponent(email));
        }

        function verifyCode() {
            var email = document.getElementById('email-input').value.trim();
            var code  = document.getElementById('code-input').value.trim();
            var errorMsg = document.getElementById('error-msg');
            var btn = document.getElementById('verify-btn');

            if (!email || !code) { errorMsg.innerText = "请填写邮箱和验证码。"; return; }

            errorMsg.innerText = "";
            btn.disabled = true;
            btn.innerText = "验证中...";

            var xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    btn.disabled = false;
                    btn.innerText = "验证并进入";
                    if (xhr.status === 200) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res.status === 'success') {
                                document.getElementById('star-modal').style.display = 'none';
                                location.reload();
                            } else {
                                errorMsg.innerText = res.message;
                            }
                        } catch (e) { errorMsg.innerText = "解析响应失败"; }
                    } else { errorMsg.innerText = "网络请求失败"; }
                }
            };
            xhr.send('action=verify_code&email=' + encodeURIComponent(email) + '&code=' + encodeURIComponent(code));
        }

        function logoutAuth() {
            if (!confirm('确定要退出授权吗？退出后需要重新验证。')) return;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res.status === 'success') { location.reload(); return; }
                        } catch (e) {}
                    }
                    alert('退出失败，请稍后重试。');
                }
            };
            xhr.send('action=logout');
        }
    </script>
</body>
</html>
