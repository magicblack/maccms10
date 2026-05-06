# Plugins, comments, orders / 插件、评论与订单类

## 插件（addons）

1. **后台 → 插件 / 应用**（名称依版本）：上传或安装 zip，按向导启用。  
2. 插件常写入 **`addons/插件标识/`**，并可能在 **管理员权限** 中新增菜单——需在 **角色** 里勾选。  
3. **升级 CMS 内核**前，确认插件兼容性；先在测试环境演练。  
4. 卸载插件遵循插件作者说明（有的需执行卸载 SQL），避免手写删目录遗留钩子。

## 评论 / 留言

- **评论管理**：审核、屏蔽广告、拉黑用户关键词（常与 **网站安全 → 违禁词** 联动）。  
- **留言本**（若启用）：定期清理垃圾，防止 XSS/外链垃圾 SEO。

## 会员、卡密、订单（若启用）

1. **系统 → 会员** 相关：开关、注册、积分规则。  
2. **订单 / 卡密 / 分销** 等菜单以您安装版本为准；支付需配置 **微信/支付宝** 密钥与回调 URL（务必 HTTPS 与公网可达）。  
3. 测试支付用 **沙箱/小额**；不要将生产密钥贴到对话或截图外传。

## English summary

**Addons**: install via admin **plugin** UI; assign **role permissions** for new menus. **Comments/Gbook**: moderate spam. **Membership/Pay**: configure gateways carefully; never paste live keys into chat logs.
