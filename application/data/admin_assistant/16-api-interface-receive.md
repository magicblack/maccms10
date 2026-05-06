# 开放 API 配置 & 站外入库配置

## 开放 API 配置 (`系统 → 开放API配置` / `menu/configapi`)

- 提供给 **APP、小程序、采集端、合作方** 拉取 **视频/文章等 JSON** 的控制台。  
- 通常包含：**总开关、`pass`/密钥**、按模块允许的 **ac（动作）**：list/detail/…  
- **安全**：密钥定期轮换；**IP 防火墙**限制；仅用 **HTTPS**；禁止把密钥写入前端源码。  
- **性能**：为高 QPS 配 **Redis 缓存 / CDN** / 拆分只读库（架构级，超纲可建议运维）。

---

## 站外入库配置 (`系统 → 站外入库配置` / `menu/configinterface`)

- **允许远程 HTTP 入库**（视频/文章/演员…）时使用，对应 `maccms.interface`。  
- **status** 开关、**pass** 通信密码、各类型 **vodtype / arttype 映射**。  
- 接收地址一般为 **`/api.php/receive/vod`** 等（以官方文档与您服务器 **伪静态** 为准）。  
- **务必**：强密码、`pass` **仅服务器间传递**；WAF **限流**；日志监控异常大批量写入。

---

## 与前台「搜索/播放」的区别

- **开放 API**：机器读 JSON。  
- **播放页**：_visitor HTML_，走的是 **index + 模板**；鉴权与用户组不同。

---

## English summary

**configapi** exposes read APIs with a **shared secret**. **configinterface** allows **receive** endpoints for remote ingestion—**very sensitive**; lock down by IP + strong pass + HTTPS.
