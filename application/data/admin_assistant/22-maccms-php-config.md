# `application/extra/maccms.php` 结构说明（无敏感值）

主配置文件为 **`application/extra/maccms.php`**（`return array(...)`）。后台 **系统** 各页保存时会 **整体回写**该文件（具体以保存逻辑为准）。

## 顶级键（常见）

| 键 | 主要内容 |
|----|----------|
| **db** | 数据库类型、主机、库名、用户、**表前缀 tablepre**、`backup_path`。 |
| **site** | `site_name`、`site_url`、`install_dir`、模板与 HTML/ads 目录、`site_status`、Logo、统计代码等。 |
| **app** | 缓存、分页、搜索规则、扩展分类/地区/年代、编辑器、**lang**、`pathinfo_depr`、`suffix`、搜索/版权等。 |
| **user** | 会员注册登录、积分提现、试看、点播/文章点数策略。 |
| **gbook** / **comment** | 留言与评论开关、审核、分页、间隔。 |
| **upload** | 缩略图、水印、`mode`（local/ftp/云）、`api.*` 密钥块。 |
| **interface** | 站外入库 `status`、`pass`、类型映射。 |
| **pay** | 支付网关参数。 |
| **weixin** | 微信对接。 |
| **email** / **sms** | 邮件与短信。 |
| **seo** | SEO 相关（若独立块，以实际文件为准）。 |
| **vod** / **art** 等 | 部分版本有模块默认（以实际文件为准）。 |
| **ai_search** | AI 搜索 / 外部源 / 向量。 |
| **admin_assistant** | 后台浮动助手开关、复用 Key、模型、速率。 |
| **timming** | 定时任务条目（若存于该文件）。 |

## 操作守则

1. **不要**把含真实密码的 `maccms.php` 提交到 Git 或发聊天。  
2. 手工编辑前 **复制备份**；PHP **语法错误**会导致全站崩溃。  
3. 多环境（测试/生产）用 **不同副本**，勿直接覆盖。

---

## English summary

**maccms.php** holds **all major CMS settings** as PHP array keys: `site`, `app`, `user`, `upload`, `interface`, `pay`, AI blocks, etc. Treat it like **production secrets** + **backup before edit**.
