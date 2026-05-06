# Search, Meilisearch, AI / 搜索、索引与 AI

## 1) 前台搜索总开关与行为

1. 进入 **系统 → 网站参数**（或合并后的「基础配置」页，以您后台为准）。  
2. 找到与 **搜索** 相关的选项，例如：是否开启搜索、搜索长度、热门搜索词等（配置键多属于 `app` 区块）。  
3. 保存后在前台搜索框验证；若仍异常，检查 **URL 设置** 中搜索页路由及 **伪静态**。

### 搜索增强（视频 LIKE 缓存）

部分版本提供 **视频搜索优化**，将模糊查询结果缓存到表 **`vod_search`**（模型 `VodSearch`），减轻数据库压力。开关与缓存时间在 **系统配置** 中（如 `vod_search_optimise` 等项，以界面为准）。

### 搜索词日志与联想

- **`search_query_log` 表**：记录用户搜索词（可关），用于 **热门词**、**登录用户搜索历史**（需在对应搜索落地页触发记录）。  
- **联想词 (suggest)**：通常对 **视频/文章/专题/演员/角色/网址** 等主表的名称字段做前缀/包含匹配 **不是** 直接读日志表；具体以当前版本 AJAX 接口为准。

---

## 2) Meilisearch（若本站已启用）

- 索引文档常包含 **视频、文章、漫画** 等，用于更快、更模糊的检索。  
- **首次启用或大规模改库后** 往往需要在后台执行 **重建/同步索引**（菜单位置可能是 **数据库**、**SEO/搜索** 或自定义入口，请以实际后台为准）。  
- Meili 服务需 **独立进程/容器** 可访问；检查 host、密钥、防火墙。

---

## 3) AI 搜索 / 前台 AI 对话（系统 → AI 搜索配置）

1. **启用** 总开关。  
2. 填写 **服务商**（如 openai）、**模型**、`API Base`、`API Key`（OpenAI 兼容接口均可尝试）。  
3. **模块开关**：按需打开 **视频、文章、专题、演员** 等参与 AI 内部资源召回。  
4. **外部链接 / 外部数据源**：按说明配置 TMDB、豆瓣等；注意 Key 与速率限制。  
5. **语义重排**：需可用的 **Embeddings** 接口与模型名。  
6. **频率限制**：建议开启，防止接口被刷。  

---

## 4) 后台助手（浮动「?」按钮）

1. 进入 **系统 → 后台助手配置**（已与「AI 搜索配置」分拆为独立菜单）。  
2. **启用后台助手**；可勾选 **复用「AI 搜索配置」中的 API Key / Base**，或在本页单独填写。  
3. 知识库文件路径：**`application/data/admin_assistant/*.md`**，可按站点业务增删改。  
4. **附带环境快照**（可选）：仅包含搜索开关、语言、缓存类型、站点名、模板目录等非敏感项，便于解释当前环境。  

助手 **不会** 自动修改数据库或配置；仅作说明与步骤引导。

---

## English summary

Frontend search: **System → site parameters** + URL rules. Optional **vod_search** cache for video LIKE. **Meilisearch** needs sync jobs if enabled. **AI Search** page configures LLM + modules + external sources. **Admin assistant** lives under **System → Admin assistant configuration** and uses markdown under `application/data/admin_assistant/`.
