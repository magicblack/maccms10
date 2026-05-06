# 会员参数、订单、支付、微信、邮件、短信

## 会员参数配置 (`系统 → 会员参数配置` / `menu/configuser`)

见 `maccms.user`：`status` 总开关、`reg_*` 注册与审核、`login_verify`、**积分/邀请/奖励/提现**、**试看**、**视频/文章收费 points_type**、头像等。  
与 **用户 → 会员组 / 会员 / 充值卡 / 订单 / 提现** 联动。

### 运营步骤（概念）

1. 先定 **会员组**（免费/VIP/包月）与 **权限、价格、积分倍率**（以表单为准）。  
2. 配置 **支付**（见下）确保 **回调成功**。  
3. 小金额 **真实支付测试** 再走正式。

---

## 在线支付配置 (`系统 → 在线支付配置` / `menu/configpay`)

- 支付宝、微信商户参数：**appId、mchId、密钥、异步通知 URL**。**异步通知必须为公网 HTTPS**（微信支付强制）。  
- **沙箱密钥不得用于生产**。  
- 退款、对账在各支付平台商户后台完成。

---

## 微信对接配置 (`系统 → 微信对接配置` / `menu/configweixin`)

- **公众号 / 开放平台** Token、AESKey、AppID 等用于 **扫码登录、绑定、推送**（以版本字段为准）。  
- **回调域名** 必须备案且 **白名单授权**。

---

## 邮件、短信 (`configemail` / `configsms`)

- **邮件**：SMTP 或 API，填发件邮箱、端口、加密方式、授权码（非邮箱登录密码若为厂商规定）。测试发信到自用邮箱。  
- **短信**：签名、模板 ID、服务商 Key；注意 **频次与费用**，防刷子盗刷短信。

---

## 会员订单、充值卡、提现 (`order` / `card` / `cash`)

- **订单**：查支付状态、补单、异常关闭。  
- **充值卡**：批量生成卡密、渠道发放、防泄露。  
- **提现**：审核、打款、拒绝原因记录。

---

## English summary

**User module** is driven by `maccms.user` + **group/order/pay**. **Pay & WeChat** need correct **callback URLs** and production keys. **Email/SMS** need provider credentials and rate limits.
