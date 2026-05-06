# 生成（静态/首页/地图）

## 菜单 (`menu/make`)

| 项 | 说明 |
|----|------|
| **生成选项** `make_opt` | **路径、分段、超时、并发（若支持）** 等全局选项；错误配置会导致磁盘占满或卡死 PHP。 |
| **生成首页** `make_index` | **PC 首页**静态文件（路径依规则写入 `article`/`html` 目录等，以实现为准）。 |
| **生成WAP首页** `make_index_wap` | 手机站首页。 |
| **生成地图** `make_map` | **sitemap / rss**（若启用）或与 SEO 配套的地图文件。 |

## 何时需要生成

- 开启或依赖 **静态化**（与 `cache_page`、`compress`、服务器策略有关）时：**改模板 / 大批量改内容 / SEO 改版** 后应 **重新生成** 对应范围。  
- **纯动态**（不设页面缓存）：可能 **不需**静态首页，但仍可能有 **地图**需求。

## 运维注意

1. **磁盘空间**：生成前先 `df -h`。  
2. **权限**：目标目录 Web 用户可写。  
3. **CDN**：生成后可能需要 **刷新 CDN 缓存**。  
4. **队列过大**：在低峰分批生成，避免超时；调大 **`max_execution_time` / `memory`**（宝塔//php.ini）。

---

## English summary

**Make** module prebuilds HTML and maps for **SEO/performance** when site uses **static-ish** delivery. Requires **writable paths** and **enough disk**. Pair with CDN invalidation after runs.
