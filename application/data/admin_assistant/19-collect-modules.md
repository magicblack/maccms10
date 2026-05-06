# 采集：自定义接口、自定义规则、推荐资源、定时挂机

## 自定义接口 (`采集 → 自定义接口` / `menu/collect`)

- **API 对接型**：填写资源站 **接口根地址、绑定分类、点播或文章 mid**、更新时间等。  
- **分类映射**：远程 `type_id` → 本地 `type_id`；错映射会 **进店错误类目**。  
- **测试**：单次拉取一页再全量。**断点续采**避免重复重压。

## 自定义规则 (`采集 → 自定义规则` / `menu/cj`)

- **HTML/正则/规则模板**抓取页面（兼容性依具体规则写法）。  
- 适合 **非标准 API** 源；维护成本较高，需在 **抓取失败**时更新规则。

## 推荐资源 (`采集 → 推荐资源` / `menu/union`)

- 预设或官方合作资源入口（以实现为准）；启用前阅读 **资费与版权条款**。

## 定时挂机 (`采集 → 定时挂机` / `menu/collect_timming`)

- 配置 **挂机任务**列表或开关（依版本）；**真正执行依赖 crontab 访问 api.php**。  
- 与 **系统 → 定时任务配置** 说明互补：一处配 **URL**，一处配 **cron**。

## 全局前提

- **系统 → 采集参数配置** 已保存。  
- 服务器 **`allow_url_fopen`/`curl`** 出站正常、**DNS** 正常。  
- **播放器/下载器** 与源站字段匹配。

---

## English summary

**Custom API** collects via HTTP APIs; **Rules** scrape HTML—brittle when site layout changes.**Union** presets partners.**Cron** executes collection—configure both CMS URLs **and** server crontab.
