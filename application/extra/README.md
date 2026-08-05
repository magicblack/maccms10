# application/extra/ 配置文件分类说明

> 本文档是「配置文件分级分层重构」评估 issue 的落地产出。结论：**不做目录级改造，只做分类说明**（原因见第二节），
> 不改变任何现有文件的读写方式，因此没有迁移期、没有新旧文件兼容问题。

## 一、结论摘要

- 本目录下 15 个 `*.php` 配置文件，**除 `captcha.php`、`queue.php` 外全部属于"运行期用户数据"**，
  必须遵守"读取 → 检查缺失键 → 只补缺失键 → 整文件回写"的机制（第三节）。
- 分类不通过目录/文件迁移实现，而是通过本文档 + 分类表实现"一看就知道去哪改"。
- 三级分类标准：

  | 级别 | 含义 | 站长改动频率 | 谁该改 / 是否安全 |
  |---|---|---|---|
  | 🖥️ **服务器/部署相关** | 跟着服务器、网络、存储环境走的参数，装机/换服务器时才需要碰 | 极低，通常只在安装或迁移服务器时改一次 | 建站时由站长本人或代维护的技术人员设置一次；填错会直接导致连不上库/存储/邮件服务，改动前建议先在测试环境验证 |
  | ⚙️ **运营相关** | 站长日常运营网站时会打开后台去调整的业务设置 | 中到高，是后台"设置"页面的主体 | ✅ 面向站长，随便改，都有后台表单，改错影响范围小且可随时改回来 |
  | 🔒 **固定/系统级** | 框架底层参数或由程序自动维护的数据，站长一般不需要、也不建议手动碰 | 几乎为零 | 🚫 不建议站长碰，只有开发者做二次开发或官方升级流程会动；改错可能导致前台 404、路由错乱或插件系统损坏 |

## 二、为什么不做目录级分层

1. **需求已明确**：用户方澄清过，不需要目录层级，只需要"分类说明，方便查找和修改"。
2. **框架层面也做不到无风险的目录嵌套**：`application/extra/` 的加载完全由 ThinkPHP5 核心完成，
   [`thinkphp/library/think/App.php:256-264`](../../thinkphp/library/think/App.php) 用的是：
   ```php
   if (is_dir(CONF_PATH . $module . 'extra')) {
       $dir   = CONF_PATH . $module . 'extra';
       $files = scandir($dir);
       foreach ($files as $file) {
           if ('.' . pathinfo($file, PATHINFO_EXTENSION) === CONF_EXT) {
               $filename = $dir . DS . $file;
               Config::load($filename, pathinfo($file, PATHINFO_FILENAME));
           }
       }
   }
   ```
   这是**非递归的 `scandir()`**，只扫描 `extra/` 一层。若把文件挪进子目录，框架不会再自动加载，
   等于要 fork 框架核心或另写一套加载器——收益（目录好看一点）远小于风险（所有 `config('xxx')` 调用点、
   所有 `mac_arr2file(APP_PATH.'extra/xxx.php', ...)` 写入点都要跟着改路径，改漏一处就是线上事故）。
3. **文件内注释也不是好的分类载体**：管理员在后台保存设置时，写盘统一走
   [`mac_arr2file()`](../common.php#L323)（`application/common.php:323-336`），内部用 `var_export()` 把整个
   PHP 数组重新序列化成 `"<?php\nreturn $con;"`。**任何手写在文件头部的注释，会在站长下一次保存该设置页面时被整体覆盖丢失**
   （`type_synonyms.php` 目前文件头就有一段说明注释，但它没有被任何 admin 控制器写回，所以还留着；
   `maccms.php`/`timming.php`/`vodplayer.php` 等一旦被写回，行内注释必丢）。
   因此凡是"运行期用户数据"文件，分类信息只能放在本文档这类外部说明里，不能塞进文件本身。

## 三、"运行期用户数据"范围认定（issue 要求的第一项）

判定标准：该文件是否会被 admin 后台功能通过 `mac_arr2file()` / `mac_save_config_data()` 整体回写。

| 文件 | 是否运行期用户数据 | 回写入口 |
|---|---|---|
| `maccms.php` | ✅ 是 | `admin/controller/System.php`（30+ 处）等几乎所有设置页 |
| `timming.php` | ✅ 是 | `admin/controller/ResourceHub.php:966`、`Timming.php` |
| `vodplayer.php` | ✅ 是 | `admin/controller/ResourceHub.php:839`、`BatchPlayer.php` |
| `vodserver.php` | ✅ 是 | `admin/controller/ResourceHub.php` |
| `voddowner.php` | ✅ 是 | `admin/controller/ResourceHub.php` / `BatchPlayer.php` |
| `bind.php` | ✅ 是 | `admin/controller/ResourceHub.php:620`、`Collect.php` |
| `blacks.php` | ✅ 是 | `admin/controller/Comment.php`、`common/util/IpBanRepository.php` |
| `domain.php` | ✅ 是 | `admin/controller/Domain.php` |
| `quickmenu.php` | ✅ 是 | `admin/controller/Index.php` |
| `addons.php` | ✅ 是（程序自动维护，非表单填写） | `Service::refresh()`（`vendor/karsonzhang/fastadmin-addons/src/addons/Service.php:242-244`，装卸插件时用 `var_export` 整文件重写） |
| `mctheme.php` | ✅ 是 | `admin/controller/TplConfig.php`、`api/controller/Config.php` |
| `type_synonyms.php` | ❌ 否，目前只读 | 仅被 `admin/controller/ResourceHub.php` 读取用于分类匹配，代码库中没有任何写入点，文件头注释可以安全保留 |
| `version.php` | ❌ 否，非站长数据 | 没有任何程序化写入点；`code`/`update_hash` 由发布方随升级包整体替换（类似代码文件，而不是"合并补写"的用户配置），站长不应手动改，也不需要合并保护 |
| `captcha.php` | ❌ 否，框架默认参数，无 admin 写入点 | — |
| `queue.php` | ❌ 否，框架默认参数，无 admin 写入点 | — |

**结论：15 个文件中 11 个是需要合并/补写保护的运行期用户数据；`type_synonyms.php` 目前只读（未来如果加"后台编辑同义词"功能，写入时需要补上幂等合并逻辑）；
`version.php` 定位更接近"随发布包整体替换的版本元数据"而非用户配置；`captcha.php`、`queue.php` 是纯框架默认值。
这 4 个文件目前都没有 admin 整文件回写点，因此本次已经给它们加上了分类头部注释（见下方"已落地的改动"）。**

## 已落地的改动（2026-08-04）

基于上面的判定，给这 4 个确认无回写风险的文件加了分类头部注释，方便直接打开文件就知道它属于哪一级、要不要改：
`captcha.php`、`queue.php`、`version.php`、`type_synonyms.php`（在原有说明基础上追加分类标签）。
其余 11 个"运行期用户数据"文件按第二节的结论，**没有**加任何头部注释——加了也会在下次后台保存时被 `var_export` 静默抹掉，
写了等于没写，所以维持原状，分类信息只在本文档里维护。

另外，`maccms.php` 的 23 个顶层分区已经按第五节描述的三级分组重新排列了物理顺序（内容/值完全不变，只是顺序调整，
过程用脚本 + 严格 `===` 校验 + 写盘前后二次校验保证零数据丢失，改动前的文件已备份）。这是目前**唯一**一个动了内容顺序的文件，
因为它是唯一一个内部混合了三种级别的文件；其余 14 个文件本身单一分类、无需内部重排。

## 四、按文件分类（三级分类总表）

| 文件 | 分类 | 一句话说明 | 常用后台入口 | 站长可以直接改吗 |
|---|---|---|---|---|
| `domain.php` | 🖥️ 服务器/部署 | 多域名 → 站点映射，跟着站点绑定的域名走 | 系统设置 → 域名管理 | ✅ 可以，绑定新域名时改 |
| `queue.php` | 🖥️ 服务器/部署 | 队列驱动（默认同步执行），只有接队列中间件才需要改 | 无（需手改） | ⚠️ 一般不用改，接队列中间件时才需要，建议懂技术的人操作 |
| `addons.php` | 🔒 固定/系统级 | 插件系统的 hook/route 注册表，随装卸插件自动生成 | 插件管理（间接） | 🚫 不要手动改，装卸插件时程序自动重写 |
| `captcha.php` | 🔒 固定/系统级 | 验证码底层参数，基本不用碰 | 无 | 🚫 不建议改，改错验证码功能会失效 |
| `version.php` | 🔒 固定/系统级 | 程序版本号、授权类型、升级校验哈希 | 无（随升级包整体替换） | 🚫 不要手动改，会导致升级校验异常 |
| `mctheme.php` | ⚙️ 运营相关 | 前台展示：logo、banner、App 下载引导、首页分类图标等 | 模板设计 / 主题配置 | ✅ 可以，站长常改的展示类设置 |
| `timming.php` | ⚙️ 运营相关 | 定时任务开关与执行时间（采集、生成、统计聚合等） | 系统设置 → 定时任务 | ✅ 可以 |
| `type_synonyms.php` | ⚙️ 运营相关 | 采集分类同义词表，接新采集源时经常要扩充 | 资源库 → 分类同义词 | ✅ 可以，目前需要直接改文件（后台暂无编辑入口） |
| `vodplayer.php` | ⚙️ 运营相关 | 视频播放器/解析方式定义 | 资源库 → 播放器管理 | ✅ 可以 |
| `vodserver.php` | ⚙️ 运营相关 | 采集/解析服务器定义 | 资源库 → 采集服务器 | ✅ 可以 |
| `voddowner.php` | ⚙️ 运营相关 | 下载方式定义（http/迅雷等） | 资源库 → 下载方式 | ✅ 可以 |
| `bind.php` | ⚙️ 运营相关 | 采集分类绑定映射数据 | 采集 → 分类绑定 | ✅ 可以，但一般通过后台操作生成，不建议手写 |
| `blacks.php` | ⚙️ 运营相关 | 评论/留言的关键词与 IP 黑名单 | 评论管理 → 黑名单 | ✅ 可以 |
| `quickmenu.php` | ⚙️ 运营相关 | 后台顶部快捷菜单自定义 | 系统设置 → 快捷菜单 | ✅ 可以 |
| `maccms.php` | 🖥️/⚙️/🔒 混合 | 站点主配置，23 个顶层分区，**已按三级分类重新排列物理顺序**（见第五节） | 系统设置（各子页） | 视具体分区而定，见第五节 |

## 五、`maccms.php` 内部再分类（这是最需要"一眼看懂"的文件）

`maccms.php` 是唯一一个混合了三种级别的大文件。**文件内的 23 个顶层分区已经按下面三个分组物理重排过**
（只调整顶层 key 的排列顺序，没有改任何值、没有改任何嵌套结构——做法与验证方式见本节末尾）。
现在用编辑器打开这个文件，从上到下就是"服务器相关 → 运营相关 → 固定/系统级"三段，不用再满屏搜索。

### 🖥️ 服务器/部署相关（文件最前面，换服务器、换存储、换 SMTP 时才需要碰）

| 顶层 key | 内容 | 谁该改 |
|---|---|---|
| `db` | 数据库连接信息（与 `application/database.php` 是两份，`database.php` 才是真正生效的 TP5 连接配置，`maccms.php['db']` 主要用于备份功能展示，改动要两处一起看） | 建站/换库时，懂技术的人 |
| `meilisearch.host` / `api_key` | 外部搜索服务地址与密钥 | 接入 Meilisearch 时，懂技术的人 |
| `email.phpmailer` | SMTP 服务器地址、端口、账号密码（`email.tpl` 邮件文案本身是运营内容，但整个 `email` 分区物理上放在服务器区，因为最容易出问题的是 SMTP 连接参数） | 配置发信服务器时，懂技术的人 |

> 另外几个"服务器属性"的键嵌套在下面的运营分区里，没有单独提出来（提出来要重排嵌套层级，收益不大、风险更高）：
> `app.cache_type`/`cache_host`/`cache_port`/`cache_username`/`cache_password`/`cache_db`（缓存服务器地址）、
> `upload.mode`/`protocol`/`api.ftp`/`api.qiniu`/`api.upyun`/`api.weibo`/`api.uomg`（存储/图床对接凭据）、
> `trusted_proxies`（反代信任 IP，本机尚未升级到包含此键的版本）。

### ⚙️ 运营相关（文件中段，后台"设置"页面的主体，站长常改）

`site`、`app`、`user`（会员注册/积分/打赏/提现规则）、`gbook`、`comment`（留言评论开关）、
`upload`（图片相关，含存储凭据+水印规则）、`interface`（资源站接口密码）、`pay`（支付渠道 appid/appkey）、
`collect`（采集规则）、`api`（接口开关/收费/权限/限流）、`connect`（QQ/微信第三方登录）、
`weixin`（公众号自动回复）、`play`（播放器展示样式）、`sms`（短信签名与模板）、`seo`（SEO 标题关键词）、
`urlsend`（搜索引擎主动推送 token）、`extra`（预留自定义扩展位）。
（`ai_search`/`ai_content`/`analytics`/`monitor`/`push` 是较新的按需开关类功能，本机的 `maccms.php`
尚未经过对应的升级补丁注入，还没有这些键；一旦补丁跑过，它们会被追加在文件末尾——见本节末尾"未来新增键"的说明。）

### 🔒 固定/系统级（文件最后面，不建议手动改，改错容易导致前台 404 或路由错乱）

| 顶层 key | 内容 |
|---|---|
| `view` / `path` / `rewrite` | 伪静态路由规则本体，属于程序底层路由定义，只有做深度二次开发时才需要碰，普通运营不应修改 |
| `plugin_cloud.registry_url` / `cache_ttl` | 插件市场服务地址，官方维护，站长一般不用改 |

### 重排是怎么做的、为什么安全

没有手工编辑 767 行文件，而是写了一个一次性脚本（逻辑如下），跑在服务器本地：

1. `include` 原文件拿到 `$old` 数组；
2. 按上面三个分组定义新的顶层 key 顺序 `$order`；
3. 先断言 `$order` 和 `array_keys($old)` 是同一组 key（一个不多一个不少）才继续；
4. 按 `$order` 重新组装 `$new`，每个 key 的值直接引用 `$old[$key]`，不做任何转换；
5. 逐 key 用 `===` 严格比较 `$new[$k] === $old[$k]`，确保值和嵌套结构完全没变；
6. 写盘前先备份原文件；写盘格式沿用 `mac_arr2file()` 同款的 `"<?php\nreturn " . var_export($new, true) . ";"`，
   保证和后台保存产生的文件字节格式一致；
7. 写盘后重新 `include` 一遍，再逐 key 严格比较一次，确认落盘结果和写之前完全一致，任何一步失败就用备份还原。

**为什么这个顺序以后能保持住**：后台保存设置时的写法统一是先 `$old = config('maccms')`（读到的就是刚重排过的顺序），
再 `array_merge($old, $new_partial)` 或直接对 `$old` 的子键赋值，最后整体 `mac_arr2file()`。PHP 的 `array_merge()`
对已存在的 key 保留第一个数组（即 `$old`）里的位置，只有全新的 key 才会追加到末尾。也就是说：
**站长以后正常在后台保存设置，不会打乱这次排好的顺序；只有升级脚本新注入的、当前还不存在的 key
（比如 `ai_search`/`push`/`monitor` 这些），会被追加在文件最后，需要等下次跑本节的重排脚本才会归位到对应分组。**
这是可以接受的、符合预期的行为，不是 bug。

## 六、必须保留的两条不变量

重构/任何后续改动都**不能破坏**以下两点，这是本次评估的核心约束：

1. **"读取 → 检查缺失键 → 只补缺失键 → 整文件回写"的幂等补写机制**
   实现分布在三处，写法完全一致（`isset()`/`array_key_exists()` 判断 + `$changed` 标志 + 条件性
   `mac_arr2file()`）：
   - [`application/data/update/database.php:1261-1432`](../data/update/database.php)（在线升级脚本，注释原文："幂等：只补缺失键，绝不覆盖站长已设值 —— extra/maccms.php 是运行期数据"）
   - [`application/install/controller/Index.php:330-390`](../install/controller/Index.php)（安装器路径，与升级路径逻辑对齐）
   - [`application/common/behavior/Init.php`](../common/behavior/Init.php)（每次请求时的**内存级**默认值兜底，不落盘，防止新版本代码读到旧配置文件里不存在的 key 时报错）
   - 各 admin 控制器保存表单时同样先 `array_merge($old, $new)` 再整体回写，例如
     [`admin/controller/System.php:210`](../admin/controller/System.php)、`Timming.php` 里对单条任务的 `array_merge($old, $param)`。

2. **在线升级不覆盖用户配置的约定**
   这不是代码里的黑白名单校验出来的，而是**打包约定**：升级包（`step1()` 下载解压的 zip）本身
   不包含**运行期用户数据文件**（第三节表格里 11 个"✅ 是"的文件 + `captcha.php`/`queue.php`/`type_synonyms.php`
   这 3 个框架默认值文件），新增的配置 key 一律通过上面第 1 点的补写脚本注入。
   `PclZip` 解压时用的是 `PCLZIP_OPT_REPLACE_NEWER`，这个选项本身并不会跳过任何文件——真正起保护作用的是
   "官方升级包里压根不打包这些文件"这个人工约定。**这意味着任何目录/文件层面的重构，都必须同步更新打包脚本/流程，
   否则这条约定会在下一次打包时被无声破坏而不会有代码报错提醒。**

   **`version.php` 是唯一的例外，不受这条约定保护，也不需要保护**：它不是用户数据，而是随每次官方发布
   正常提交进代码库、和其他源码文件一样打进升级包整体覆盖的版本元数据——例如 `94b1ea0`
   （`升级版本至 2026.1000.4055`）就是一次普通的源码提交，直接把 `code`/`update_hash` 改到新值，
   没有走任何"读取 → 补缺失键"的合并逻辑。升级包**必须**包含它，否则在线升级后 `config('version.code')`
   永远不会前进，升级校验会失效。第三节表格里把它标为"❌ 否，非站长数据"就是在强调这一点：它虽然物理上
   放在 `extra/` 目录，但不属于上面"包内容排除"的那批文件。

## 七、后续新增配置的操作规范

给后面维护 `maccms.php` 或新增文件的人的checklist：

1. 新增配置项一律走第六节的"幂等补写"模式（`isset` 判断 + 只在缺失时填默认值 + `$changed` 标志），
   不要用无条件覆盖的写法，否则会覆盖站长已经改过的值。
2. 新增顶层 key 时，判断它属于第一节的哪一级（服务器/运营/固定），同步更新本文档第四、五节的表格。
3. 不要往这些"运行期用户数据"文件里加头部注释——会在下次表单保存时被 `mac_arr2file()` 静默清空，
   注释写了等于没写。
4. 如果新增的是一个全新文件（不是往 `maccms.php` 加 key），文件必须直接放在 `application/extra/` 
   一层目录下（不能建子目录，见第二节的框架限制），并在本文档补一行分类。

## 八、非本次范围的后续可选建议（仅供参考，不在本 issue 内实施）

- 可以考虑在后台"系统设置"页面按本文档的三级分类做 Tab/分组展示（纯前端/UI 层面的改动，
  不涉及配置文件本身的读写方式，风险很低，可以单独立一个前端 issue）。
- 官方打包流程建议加一条自动化检查：升级 zip 里如果意外包含了 `application/extra/*.php` 中除 `version.php`
  外的文件，构建脚本报错阻断，把第六节第 2 点的"人工约定"变成可执行的硬保证。
