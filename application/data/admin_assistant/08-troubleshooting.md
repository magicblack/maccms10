# Troubleshooting / 常见问题排查

**安全与木马排查**另见 **`21-safety-urlsend-addon.md`**。**目录/缓存/日志**见 **`23-directories-and-runtime.md`**。

Answer in **clear numbered steps**. Do not assume server panel brand; mention **宝塔 / LNMP / 自建** generically.

## 空白页 / 500

1. 打开 `runtime/log/` 下当天日志（或宿主 PHP error_log）。  
2. 检查 **PHP 版本** 是否满足程序要求，`openssl`/`curl`/`mysqli`/`gd` 等扩展是否开启。  
3. 若为 **syntax error**：最近是否改了 `application/extra/*.php` 或模板 PHP 混入。  

## 安装/保存配置失败「无写入权限」

1. **`application/extra`**、**`runtime`**、`template`/`upload` 等目录需对 **Web 用户**可写（Linux: `chown`/`chmod`；勿 777 到公网风险目录）。  

## 采集无数据 / 全部失败

1. **采集参数配置** 是否保存。  
2. 节点 **接口 URL** 是否可访问（服务器 **出站** 是否封禁、DNS 是否正常）。  
3. **分类映射** 是否为空或错误。  
4. 资源站是否 **换接口/需签名**（联系资源站文档）。  

## 播放黑屏 / 不能播

1. **系统 → 播放器** 当前方案是否匹配资源 **格式**（直链 / m3u8 / 跳转）。  
2. 浏览器 **控制台**（F12）是否跨域或被 **广告拦截**。  
3. **HTTPS** 页面下混入 **HTTP** 播放地址常被拦截。  

## 前台样式乱 / 改模板无效

1. **清理 CMS 缓存** 与 **浏览器缓存**。  
2. 是否开启了 **CDN** 未刷新。  
3. 是否编辑了 **错误的模板目录**（PC/WAP 模板目录弄反）。  

## AI / 后台助手无响应

1. **AI 搜索配置**：前台问答与嵌入重排用到的 Key、Base、`/v1` 前缀是否正确（兼容 OpenAI 的网关）。  
2. **后台助手配置**：助手是否启用；若勾选 **复用 AI 搜索 Key**，请先确保 **AI 搜索配置** 中已填写有效凭据；否则在助手页填写 **助手专用 Key**。  
3. 服务器 **能否访问外网**（防火墙出站、地区限制）。  
4. **频率限制 / 超时** 是否过小；适当增加 **timeout**/`max_tokens`。  

## 定时任务不跑

1. **crontab** 是否真的执行（看系统 mail 日志或面板执行记录）。  
2. wget/curl **URL** 是否带完整参数与 **https** 证书问题。  
3. `api.php` 是否被 **WAF** 拦截。  

---

When the user issue is **not** in this knowledge base, say so honestly and suggest **which admin area** to screenshot (菜单 + URL 栏) rather than inventing filenames or SQL.
