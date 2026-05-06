# System → configuration pages / 系统下属配置页（全书式参考）

后台左侧 **系统** 分组下常见入口。**界面标题以当前语言包的 `menu/...` 为准**（助手回答也会按该语言包的 `menu/*` 文案输出）；本文件中的中文路径仅作阅读理解，与 `menu/config` 等同即可。

---

## 1. 网站参数配置 (`menu/config`)

- **站点信息**：站点名、域名、WAP 域名、关键词、描述、ICP、QQ、邮箱、统计代码、Logo。  
- **安装目录 / install_dir**：必须与 **实际 URL 路径** 一致（含子目录），否则 CSS/链接错误。  
- **模板目录 template_dir、html_dir、ads_dir**：决定 `template/{模板名}/{html|ads}/`。  
- **手机站 mob_status / mob_template_dir**：开关与 WAP 模板。  
- **站点状态 site_status**：关闭站点时显示维护提示。  
- **App 区块**（多与 `maccms.app` 对应）：缓存类型与参数、页面缓存、压缩、**搜索** 开关与时间间隔、热门搜索词、**扩展分类/地区/语言/年代** 可选列表、后台分页条数、编辑器类型、**后台语言** lang、登录验证码等。  
- 保存后写入 **`application/extra/maccms.php`**；失败则检查目录写权限。

---

## 2. SEO 参数配置 (`menu/configseo`)

- 首页/栏目/内容页 **TDK** 规则、**sitemap**、链接形态相关 SEO 选项（以表单为准）。  
- 改动后若生成静态或缓存，需 **重新生成/清缓存**。

---

## 3. AI SEO 配置 (`menu/configaiseo`)

- 列表/详情批量或单行 **AI 生成 SEO**（标题/描述等），使用独立 **API Base / Key / 模型**（与会话型 AI 搜索可不同）。  
- 保存前建议小批量试跑，避免消耗过大。

---

## 4. AI 搜索配置 (`menu/configaisearch`)

- 前台 **AI 搜索/对话**、扩展词、语义向量重排、外部数据源（TMDB、豆瓣等）、速率限制。

---

## 5. 后台助手配置 (`menu/configassistant`)

- 浮动 **`?`** 管理端帮助；知识库为 **`application/data/admin_assistant/*.md`**，可选附带非敏感环境快照。  
- 可与 **AI 搜索配置** 中的 LLM 凭据 **复用**（本页开关），或单独填写模型 / API Base / Key。

---

## 6. 会员参数配置 (`menu/configuser`)

- 会员开关、注册、审核、手机/邮箱校验、登录验证码。  
- **积分、邀请、奖励、提现** 比例与门槛。  
- **试看 trysee**、**视频/文章积分消费** 策略。  
- 与 **会员组、订单、充值卡** 联动。

---

## 7. 评论留言配置 (`menu/configcomment`)

- **评论**：开关、审核、是否登录、验证码、分页、时间间隔防刷。  
- **留言本 gbook**（若与评论分表配置）：类似项在 `maccms.gbook` / `maccms.comment` 结构（以界面为准）。

---

## 8. 附件参数配置 (`menu/configupload`)

- 缩略图、水印、本地上传、**远程 FTP/七牛/又拍/微博图床** 等 `mode` 与 `api` 子数组。  
- 远程 URL 前缀、防盗链、图床 API。  
- 改 `mode` 后已存在附件不会自动迁移，需自行规划。

---

## 9. URL 地址配置 (`menu/configurl`)

- 各模块 **路由形式**（视频/文章/专题/演员/角色/网址/剧情/漫画等）。  
- `pathinfo_depr`、伪静态、`suffix` 等与 **Web 服务器规则** 必须一致。  
- 改 URL 规则会导致 **旧外链失效**，需做 301 或公告。

---

## 10. 播放器参数配置 (`menu/configplay`)

- 全局播放器相关默认、预置（与 **视频 → 播放器** 中具体代码配合）。  
- 全站策略级选项以本页 + **播放器管理** 为准。

---

## 11. 采集参数配置 (`menu/configcollect`)

- **按 Tab**：视频、文章、演员、角色、网址、**漫画**、评论、**采集词库**。  
- 控制入库 **审核状态、随机点击/顶踩/评分、同步图片、自动 TAG、分类过滤、同义词/随机词** 等。  
- **大批量采集前必先保存本页。**

---

## 12. 站外入库配置 (`menu/configinterface`)

- 允许外部系统 **POST 入库** 的开关、**通信密钥**、分类映射字符串（`动作片=动作` 形式）。  
- 密钥需保密；配合防火墙限制来源 IP。

---

## 13. 开放 API 配置 (`menu/configapi`)

- 对外 **JSON/API** 供给（列表、详情等），**pass** 或 token、各模块开关。  
- 用于 APP、小程序、合作方拉数据；注意 **_rate 与鉴权**。

---

## 14. 整合登录配置 (`menu/configconnect`)

- QQ/微信等 **第三方登录**（以当前版本集成项为准）。  
- 需相应平台 **AppID/Secret/回调 URL**（HTTPS）。

---

## 15. 在线支付配置 (`menu/configpay`)

- 支付宝/微信等 **商户号、密钥、回调**；沙箱与生产分离。  
- 回调地址须公网可达且与 **URL/HTTPS** 一致。

---

## 16. 微信对接配置 (`menu/configweixin`)

- 公众号/小程序相关 **Token、AppID** 等（依版本字段为准）。

---

## 17. 邮件发送配置 (`menu/configemail`)

- SMTP 或服务商 API；用于注册验证、通知等。

---

## 18. 短信发送配置 (`menu/configsms`)

- 短信宝、阿里云等 **模板、签名、Key**；注意次数费用。

---

## 19. 定时任务配置 (`menu/timming`)

- 列出需被 **服务器 crontab** 访问的 **URL 与说明**（如采集、生成、清理）。  
- 实际执行依赖 **wget/curl 定时访问 `api.php?...`**；不在此页点击即完成全站定时。

---

## 20. 站群管理配置 (`menu/domain`)

- 多域名绑定不同 **站点参数覆盖**（模板、WAP、路径等）。  
- 用于站群或分区品牌；配置错误会导致 **错模板或死链**。

---

## English summary

**System** menu maps to `maccms.php` sections: `site`, `app`, `user`, `upload`, `interface`, `pay`, etc. Always **save** after edits; fix **file permissions** if save fails. **URL** and **collect** pages affect the whole site—test in staging.
