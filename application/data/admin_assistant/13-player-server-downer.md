# 播放器、服务器组、下载器

## 播放器 (`视频 → 播放器` / `menu/player`)

### 作用

- 每一条 **播放器记录** 对应一种 **解析或播放方案**（如直链、m3u8、跳转、iframe、第三方解析脚本）。  
- 视频表单里选择的 **播放器编码**（`from`/组名）必须与这里 **名称一致**。  
- **排序**：数字越小越靠前（常见规则，以界面为准）。

### 配置步骤

1. **系统 → 播放器参数配置**：查看全局策略（与具体播放器代码配合）。  
2. **视频 → 播放器**：**添加** 或编辑播放器，粘贴/维护 **解析 HTML/JS**（程序会提供变量如播放地址）。  
3. 在 **添加视频** 时，**播放组** 中选择对应播放器；多集用表单说明的分隔符（常见 **`$$$`** 分隔多组）。  
4. 前台打开详情页，**F12 控制台** 查跨域、混合内容 (HTTPS/HTTP)、广告拦截。

### 常见问题

- **黑屏**：播放器代码与地址格式不匹配；或 **HTTPS 页加载 HTTP 资源** 被拦。  
- **只能播一条**：多集拼接符错误或多组字段未填满。  
- **版权提示**：`maccms.app.copyright_notice` 等开关与业务策略。

---

## 服务器组 (`视频 → 服务器组` / `menu/server`)

- 为多线路播放提供 **`server`** 代号与显示名（如「线路一」「蓝光」）。  
- 视频里 **播放服务器** 字段与播放器模板里引用的 **`server`** 变量一致才可切换线路。

---

## 下载器 (`视频 → 下载器` / `menu/downer`)

- 配置 **下载方式**（或对接下载插件），用于需要 **下载离线** 的场景；与点播播放器不同但以同一内容源为基础。

---

## English summary

**Players** define how URLs become playable video; names must match VOD **play_from** selections. **Server groups** label multi-line sources. Fix playback issues first in **browser console**, then URL scheme (HTTPS), then parser code.
