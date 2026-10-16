# Python 可视化积木编辑器

> 让 Python 像搭积木一样简单  
> 作者：baibian

一个基于 Blockly 的 Python 可视化积木编辑器。  
拖拽积木即可生成 Python 代码，并支持在浏览器中直接运行。

---

## 项目简介

本项目是一个单文件 PHP 应用，使用 Google Blockly 作为可视化积木核心，  
将积木逻辑转换为 Python 代码，并通过 Pyodide 在浏览器中执行 Python。

适合用于：

- Python 入门教学
- 少儿编程演示
- 快速生成 Python 代码片段
- 可视化编程实验
- 开源学习与二次开发

---

## 项目基于

本项目并非从零造轮子，而是基于以下成熟开源项目和技术构建：

### 1. 积木编辑器：Google Blockly

- 整个拖拽积木、工作区、右键菜单、变量与函数系统，全部来自 Google Blockly。
- 项目通过 `Blockly.inject()` 初始化工作区，并加载 Blockly 官方的 Python 代码生成器。
- 积木转 Python 代码，使用的是 Blockly 的 `python_compressed.js`。

### 2. 浏览器运行 Python：Pyodide

- 右侧「运行」按钮不是在服务器执行 Python，而是使用 Pyodide。
- Pyodide 是 CPython 编译成 WebAssembly 的版本，可以直接在浏览器中运行 Python。
- 首次运行会从 CDN 下载约 10MB 环境，之后浏览器会缓存。

### 3. 后端与授权：PHP + GitHub API

- `index.php` 负责会话管理、Star 验证、退出授权。
- 通过 cURL 调用 GitHub API，检查用户是否给指定仓库点过 Star。
- 没有数据库，纯单文件应用。

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
> **它基于 Google Blockly 做积木，基于 Pyodide 跑 Python，基于 PHP 做授权和会话，前端是原生三件套，整体是单文件 PHP 应用，并由 Gemini 和 DeepSeek 双模型协作开发。**

---

## 功能特性

- ✅ 满满当当的积木分类：逻辑、循环、数学、文本、列表、颜色、变量、函数
- ✅ 拖拽积木自动生成 Python 代码
- ✅ 右侧代码面板支持复制、下载 `main.py`
- ✅ 内置运行控制台，点击「运行」即可在浏览器执行 Python
- ✅ 全中文界面，右键菜单、分类、提示均已汉化
- ✅ GitHub Star 验证授权
- ✅ 支持暗语 `BAIBIAN` 直接解锁
- ✅ 支持「退出授权」，清除会话并重新验证
- ✅ 单文件部署，无需数据库
- ✅ 黑白方正极简 UI 风格

---

## 技术栈

- PHP：会话管理、GitHub API 校验、AJAX 接口
- Blockly：可视化积木编辑器
- Blockly Python Generator：积木转 Python 代码
- Pyodide：浏览器内运行 Python
- 原生 HTML / CSS / JavaScript

---

## 环境要求

- PHP 7.4 或更高版本
- 开启 `session`
- 开启 `curl` 扩展
- 支持 HTTPS 的 Web 服务器（推荐）
- 现代浏览器：Chrome、Edge、Firefox、Safari

---

## 快速开始

### 1. 上传文件

将 `index.php` 上传到你的网站目录，例如：

```text
/var/www/html/index.php
