# PyBlockly

> Python like building blocks.  
> 让 Python 像搭积木一样简单。

一个基于 Google Blockly 和 Pyodide 的可视化 Python 积木编辑器。  
拖拽积木即可生成 Python 代码，并支持在浏览器中直接运行。

由 **Gemini & DeepSeek** 双模型协作开发。  
作者：**baibian**

---

## 项目基于

本项目并非从零造轮子，而是基于以下成熟开源项目和技术构建：

### 1. 积木编辑器：Google Blockly
- 拖拽积木、工作区、右键菜单、变量与函数系统，全部来自 Google Blockly。
- 通过 `Blockly.inject()` 初始化工作区，加载官方 Python 代码生成器。
- 积木转 Python 代码使用 `python_compressed.js`。

### 2. 浏览器运行 Python：Pyodide
- 「运行」按钮不是服务器执行 Python，而是使用 Pyodide。
- Pyodide 是 CPython 编译为 WebAssembly 的版本，可直接在浏览器运行 Python。
- 首次运行从 CDN 下载约 10MB 环境，之后浏览器缓存。

### 3. 后端与授权：PHP + SMTP
- `index.php` 负责会话管理、邮箱验证码生成与校验、SMTP 发信。
- 使用 PHP 原生 `stream_socket_client` 连接 QQ 邮箱 SMTP，无需第三方库。
- 无数据库，纯单文件应用。

### 4. 前端：原生 HTML / CSS / JavaScript
- 没有使用 Vue、React 等框架。
- 黑白方正 UI 是手写 CSS 覆盖 Blockly 默认样式。
- 复制、下载、控制台、AJAX 等交互全部由原生 JavaScript 实现。

### 5. CDN 依赖
- Blockly：`https://unpkg.com/blockly@10.4.3`
- Pyodide：`https://cdn.jsdelivr.net/pyodide/v0.26.2/full/`

### 6. 开发协作：Gemini + DeepSeek 双模型
- 本项目由 **Gemini** 和 **DeepSeek** 双模型协作开发完成。
- 从界面设计、功能规划，到代码实现与汉化，均由两个模型共同参与。

> 一句话总结：  
> **基于 Google Blockly 做积木，基于 Pyodide 跑 Python，基于 PHP + SMTP 做邮箱验证和会话，前端是原生三件套，整体是单文件 PHP 应用，并由 Gemini 和 DeepSeek 双模型协作开发。**

---

## 功能特性

- 满满当当的积木分类：逻辑、循环、数学、文本、列表、颜色、变量、函数
- 拖拽积木实时生成 Python 代码
- 支持复制、下载 `main.py`
- 内置运行控制台，点击「运行」即可在浏览器执行 Python
- 全中文界面，右键菜单、分类、提示均已汉化
- **邮箱验证码授权**：输入邮箱获取验证码，验证通过后使用
- 支持「退出授权」，清除会话并重新验证
- 单文件部署，无需数据库
- 黑白方正极简 UI 风格

---

## 技术栈

- PHP：会话管理、邮箱验证码、SMTP 发信、AJAX 接口
- Blockly：可视化积木编辑器
- Blockly Python Generator：积木转 Python 代码
- Pyodide：浏览器内运行 Python
- 原生 HTML / CSS / JavaScript

---

## 环境要求

- PHP 7.4 或更高版本
- 开启 `session`
- 开启 `stream_socket_client`（用于 SMTP 发信）
- 一个支持 SMTP 的邮箱（推荐 QQ 邮箱）
- 现代浏览器：Chrome、Edge、Firefox、Safari

---

## 快速开始

### 1. 上传文件

将 `index.php` 上传到你的网站目录。

### 2. 配置 SMTP

打开 `index.php`，找到顶部 SMTP 配置：

```php
$SMTP_HOST = 'smtp.qq.com';
$SMTP_PORT = 465;
$SMTP_USER = 'your_qq@qq.com';       // 改成你的 QQ 邮箱
$SMTP_PASS = 'your_smtp_password';   // 改成你的 16 位 SMTP 授权码
$SMTP_FROM = 'your_qq@qq.com';       // 改成你的 QQ 邮箱
$SMTP_NAME = 'PyBlockly';
