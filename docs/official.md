# official 运维说明

本机自用。official 与 testing 宝塔机（`docs/server.md`）不是同一套。

**2026-09-08 全面核实过一遍：架构已改，旧文档里的 Docker 全栈 / 单机 MySQL / certbot 三件事全部作废。**

## 拓扑

```
CloudFront (d3pd63pqragla9.cloudfront.net, 七域名)
   └─ ALB (私网 172.31.x)  ← TLS 在这里/CloudFront 终止
        └─ official  43.198.233.182 / 172.31.28.237   ssh official
             nginx 1.30.4 (:80) + php7.0-fpm (127.0.0.1:9000)   ← 原生包，不是 Docker
             站点代码 /var/local/www/maccms10  (/var/www/html 软链)
             cron: 仅 upload → S3 同步
        └─ official.db  172.31.30.245   ssh official.db（ProxyJump official，无公网）
             mysql:5.7  Docker 容器 maccms10-mysql（裸 docker run，无 compose）
             redis-server 6379（系统包，maccms 目前没用它）
```

- 站点：https://298965.com/ ；后台 https://298965.com/xyadmin.php
- **发布分支：`self-operated-site`**（fork `starkbreaklegsquad/maccms10`）。不要再往 `issue78` 发。
- official 上 **没有 docker**（`docker: command not found`），别再抄 `docker compose exec web …`。
- 只有 official.db 上的 ubuntu 不在 docker 组，那边 docker 命令要 `sudo`。

---

## 0. 与旧文档的差异（照旧文档操作会踩的坑）

| 旧文档 | 现状 |
|--------|------|
| `ssh official` = `43.198.204.237` / `43.199.207.102` | 公网 IP 又换了：2026-10-09 起 `43.198.233.182`（同一台机，私网仍 172.31.28.237）；`~/.ssh/config` 已更新。连不上（banner exchange 超时）先确认 IP 没换 |
| 全栈 docker compose（mysql/web/nginx，`network_mode: service:mysql`） | 全没了。web 机原生 nginx+fpm，DB 单独一台 |
| PHP 连库 `127.0.0.1:3306` | 现在是 **`172.31.30.245:3306`**（official.db 私网） |
| `/usr/local/etc/maccms.php` 是真正生效的配置（容器挂载） | **`/usr/local/etc` 现在是空目录**。真正生效的是 `application/extra/maccms.php`（仓库树里那份） |
| `/usr/local/etc/php-maccms.ini` | 现在是 `/etc/php/7.0/fpm/conf.d/99-maccms.ini`（内容相同） |
| `/usr/local/etc/nginx/…` + `obtain-ssl.sh` + Let's Encrypt | 全没了。nginx 配置在 `/etc/nginx/`，**证书不在机器上**，TLS 由 CloudFront/ALB 终止 |
| 443 监听、`ssl.conf` | 机器只听 **80**。不要再去找 ssl.conf |
| crontab 里有 timming / monitor 心跳 | 迁移后一度全丢，**2026-09-08 已补回**（打本机 80 + Host 头，见 §6） |
| `docker compose restart web` 清缓存/生效 | `sudo systemctl reload php7.0-fpm` / `nginx -s reload` |

---

## 1. 路径（official）

| 用途 | 路径 |
|------|------|
| 站点代码 | `/var/local/www/maccms10`（`/var/www/html` → 软链） |
| 站点配置 | `application/extra/maccms.php`（仓库树内，**无挂载覆盖**） |
| 连库 | `application/database.php` |
| nginx 站点 | `/etc/nginx/conf.d/default.conf` |
| 伪静态 / php 段 | `/etc/nginx/snippets/maccms.conf` |
| PHP ini | `/etc/php/7.0/fpm/conf.d/99-maccms.ini` |
| fpm 池 | `/etc/php/7.0/fpm/pool.d/www.conf`（user/group **`ubuntu`**，见 §1.1；listen `127.0.0.1:9000`） |
| nginx 日志 | `/var/log/nginx/{access,error}.log` |
| ACME webroot | `/var/www/acme`（保留了 location，当前无 certbot） |

整棵站点树（含 `runtime/`、`upload/`）属主统一是 **`ubuntu`**，php-fpm 也以 `ubuntu` 运行 —— 见下。

### 1.1 php-fpm 必须以 `ubuntu` 运行（2026-09-08 修复的「配置文件写入失败」根因）

Docker 时代 PHP 镜像的 UID 是 1000，正好等于 `ubuntu`，所以后台写文件一直是通的。迁到原生 fpm 后默认跑 `www-data`，而代码树属主是 `ubuntu`、目录 755 —— **PHP 对整棵代码树没有写权限**，后台一切写文件的功能全部静默失败：

- 主题设计写 `template/<主题>/` 和 `application/data/theme_design/` → 弹「配置文件写入失败，请重试!」
- 「系统 → 网站信息 / 播放器 / 定时任务 / 采集绑定」写 `application/extra/*.php`（`mac_arr2file()`）→ 保存无效
- 「URL 地址配置」写 `application/route.php` → 丢路由
- 「生成」静态页写项目根下 `vodhtml/`、`arttypehtml/`、`index.html` → 没有产物

只有 `upload/`、`runtime/`（属主 www-data）能写，所以上传图片正常，容易误判成「只有主题设计坏了」。

修法（与旧 Docker 行为一致，一次性）：

```bash
sudo sed -i 's/^user = www-data$/user = ubuntu/; s/^group = www-data$/group = ubuntu/' /etc/php/7.0/fpm/pool.d/www.conf
sudo chown -R ubuntu:ubuntu /var/local/www/maccms10/runtime /var/local/www/maccms10/upload
sudo systemctl restart php7.0-fpm
```

- 备份在 `/etc/php/7.0/fpm/pool.d/www.conf.bak-*`。`listen.owner/group` 保持 `www-data`（nginx 连 socket 用，这里是 TCP，无影响）。
- nginx worker 仍是 `www-data`，只读文件（644/755）不受影响。
- **切换后必须清掉旧 session 文件**，否则登录会一直报「验证码错误」：`/var/lib/php/sessions` 是 `1733` sticky，新进程能建新文件，但浏览器还揣着旧 `PHPSESSID`，对应的 `sess_*` 属主是 `www-data`、mode 600 —— 新进程**打不开也写不进**，于是验证码存不进 session、每次校验必然失败（请求还是 200，不报错，极难猜）。一条命令解决，用户无需清 cookie：
  ```bash
  ssh official 'sudo find /var/lib/php/sessions -type f -user www-data -delete'
  ```
  删掉后 PHP 会用同一个 session id 重建属主正确的文件。已登录的会话会失效，重新登录即可。
- 判断这个坑的方法：拿一个旧 sid 打一次验证码接口，看 `sess_<sid>` 是否被更新——文件仍是 0 字节 / 旧属主就是中招了。
- 复查写权限：
  ```bash
  ssh official 'cd /var/local/www/maccms10; for d in . application/extra application/data/theme_design template vodhtml runtime upload application/route.php; do sudo -u ubuntu test -w "$d" && echo "W-OK   $d" || echo "DENIED $d"; done'
  ```

**别反着修**：把代码树 chown 给 `www-data` 会让 ubuntu 的 `git reset --hard` 发布流程失效；也别只给某几个目录 `g+w`，`git reset` 新建的文件会退回 `ubuntu:ubuntu`，每次发布后重新坏一遍。

official.db：MySQL 数据 `/var/local/lib/mysql5`（3.5G），日志 `/var/local/logs/mysql5`，密钥 `/var/local/.env`（`600`，root）。

---

## 2. 数据库 / Redis

| | |
|--|--|
| 主机 | `172.31.30.245:3306`（official.db，容器端口映射 `0.0.0.0:3306`，**只靠安全组挡外网**） |
| 库 / 业务用户 | `maccms10` / `maccms10`，前缀 `mac_` |
| 业务密码 | 写在 `application/database.php` 和 `application/extra/maccms.php` 的 `db` 段，两处必须一致 |
| 账号权限 | ⚠️ 2026-09-08 实测：`maccms10@'%'` 拿的是 **`GRANT ALL PRIVILEGES ON *.* `**，`root@'%'` 也存在。旧文档「只允许 127.0.0.1、权限仅 `maccms10.*`」已不成立 |
| root 密码 | official.db 的 `/var/local/.env` 里 `MYSQL_ROOT_PASSWORD` |
| 容器 | `maccms10-mysql`（`mysql:5.7`，`restart=unless-stopped`，**没有 compose 文件**，重建要手写 `docker run`，先把 mounts 抄下来） |

```bash
# 进库（只能在 official.db 上；official 没装 mysql 客户端）
ssh official.db 'sudo docker exec -it maccms10-mysql sh -c "mysql -uroot -p\$MYSQL_ROOT_PASSWORD"'
# 在 official 上跑一次性 SQL（无 mysql client，用 PHP PDO 读 database.php）
ssh official 'php -r "\$c=include \"/var/local/www/maccms10/application/database.php\";
 \$p=new PDO(\"mysql:host={\$c[hostname]};port={\$c[hostport]};dbname={\$c[database]}\",\$c[username],\$c[password]);
 var_dump(\$p->query(\"select count(*) from mac_vod\")->fetchColumn());"'
```

**连通性已验证（2026-09-08，从 official）**：TCP 3306/6379 均通；用站点凭据 PDO 直连 `172.31.30.245:3306` 成功（<1ms，`current_user()=maccms10@%`，server 5.7.44，`mac_vod` 102450 行）；Redis `PING` 返回 `+PONG`。

两台机在同一 VPC 私网，MySQL 容器 `0.0.0.0:3306` 映射 + `maccms10@'%'` 全库权限 + root@'%' + Redis 无密码，**安全边界完全落在安全组上**。建议收紧：MySQL 端口映射改 `172.31.30.245:3306:3306`、业务账号降到 `maccms10.*`、Redis 加 `requirepass` 并 `bind` 私网。

**Redis 目前是装了没用。** `official.db` 上 `redis-server` 在跑（`bind 0.0.0.0`、`protected-mode no`、**无 requirepass**、`appendonly no`、`maxmemory 0`），但 `info keyspace` 是空的，站点 `config.php` 的 `cache.type=File`、`session.type=file`，`maccms.php` 里也没有 cache 段。也就是说：

- 要接 Redis，得改 `application/config.php` 的 `cache`/`session` 驱动，不是改后台。
- 在接之前，`bind 0.0.0.0` + 无密码是**只靠安全组兜底**的裸奔配置。要么加 `requirepass` + `bind 172.31.30.245 127.0.0.1`，要么确认安全组只放行 official 私网。

---

## 3. 域名 / TLS

nginx `server_name`（同一套主站）：
`298965.com` `856981.com` `827177.com` `652308.com` `635845.com` `863994.com` `910965.com`
主域名（`site_url` / `site_wapurl`）= **298965.com**。`extra/domain.php` 保持空数组。

DNS → `d3pd63pqragla9.cloudfront.net`（CloudFront）→ ALB → EC2:80。

`default.conf` 的跳转逻辑：`X-Forwarded-Proto: https` **或** `X-Origin-Proto: https` 时直接服务，否则 `301 https://$host$request_uri`。用了自定义头 `X-Origin-Proto` 是因为 ALB 会覆盖 `X-Forwarded-Proto`。

### PHP 必须知道「真实协议」（2026-09-08 修复的 Mixed Content 根因）

CloudFront→ALB 之间走的是 HTTP，**ALB 会把 `X-Forwarded-Proto` 覆盖成 `http`**，到 PHP 时 `HTTPS` 未置、`REQUEST_SCHEME=http`、`SERVER_PORT=80`，于是 TP5 `Request::isSsl()` 一律 false，所有用 `$request->scheme()` 拼的绝对 URL 都变成 `http://` —— 表现为后台「主题设计」预览 iframe 被浏览器拦掉（`Mixed Content: ... requested an insecure frame`），前台的 canonical / 跳转 / 分享链接同理。**只有 CloudFront 加的自定义头 `X-Origin-Proto: https` 是可信的。**

修法在 nginx（一处修好，全站 URL 生成都受益）。`conf.d/default.conf` 的 server 段里：

```nginx
set $real_scheme http;
set $https_on "";
if ($http_x_forwarded_proto = "https") { set $real_scheme https; set $https_on on; }
if ($http_x_origin_proto = "https")    { set $real_scheme https; set $https_on on; }
```

`snippets/maccms.conf` 的 php location 里（替换掉原来的 `fastcgi_param HTTPS $https if_not_empty;`，`$https` 在纯 80 监听下永远是空的）：

```nginx
fastcgi_param HTTPS $https_on if_not_empty;
fastcgi_param REQUEST_SCHEME $real_scheme;
fastcgi_param HTTP_X_FORWARDED_PROTO $real_scheme;
```

验证（经 CloudFront 应得 `isSsl=true`；本机直连 80 无头时仍应是 http，即 301）：

```bash
# 临时探针，用完删掉
ssh official 'cat > /var/local/www/maccms10/_hdr.php <<PHP
<?php define("APP_PATH","/var/local/www/maccms10/application/");
require "/var/local/www/maccms10/thinkphp/base.php";
\$r=\think\Request::instance(); echo "isSsl=".var_export(\$r->isSsl(),true)." scheme=".\$r->scheme()."\n";
PHP'
curl -s https://298965.com/_hdr.php
ssh official 'rm -f /var/local/www/maccms10/_hdr.php'
```

配置备份在 `/etc/nginx/conf.d/default.conf.bak-*` 和 `/etc/nginx/snippets/maccms.conf.bak-*`。

- **证书不在这台机上**，不要再跑 `obtain-ssl.sh` / certbot，`/etc/letsencrypt` 不存在。续期是 CloudFront/ACM 的事。
- 本机自测必须带头，否则只会拿到 301：
  ```bash
  ssh official 'curl -s -o /dev/null -w "%{http_code}\n" -H "X-Origin-Proto: https" http://127.0.0.1/'
  ```

---

## 4. 静态资源 / S3

`/etc/cron.d/s3-upload-sync`：

```cron
*/2 * * * * root aws s3 sync /var/local/www/maccms10/upload s3://hkcms-media-sg/upload/ >> /var/log/s3-sync.log 2>&1
```

- 走 EC2 实例角色 `hk-web-ec2-role`（账号 `131540503062`）。该角色**只有 S3 权限**，`ec2:Describe*` / `elbv2` / `cloudfront` 全部 AccessDenied——想查 ALB / CF 配置只能去控制台，机器上查不出来。
- 单向 sync，无 `--delete`：本地删图不会删 S3。
- 影片图片当前大量直接引外站（如 `shandianpic.vip`），并不走本机 upload。

---

## 5. 日常命令

```bash
ssh official 'sudo nginx -t && sudo nginx -s reload'      # 改 /etc/nginx/ 后
ssh official 'sudo systemctl reload php7.0-fpm'           # 改 99-maccms.ini 后
ssh official 'sudo systemctl status nginx php7.0-fpm --no-pager'
ssh official 'sudo tail -f /var/log/nginx/error.log'
ssh official 'php -v; php -m'
# 清前台缓存
ssh official 'cd /var/local/www/maccms10 && for d in runtime/cache runtime/temp runtime/twig; do [ -d "$d" ] && find "$d" -mindepth 1 -type f -delete; done'
```

`cache_core=1` / `cache_time=3600` / `cache_page=0`。`126fd7a5` 起 9 类列表缓存在后台增删改时按表名 tag 定点作废；遇到「后台改了前台没动」先确认代码在这个 commit 之后。

---

## 6. 定时任务：**当前是缺的**

`crontab -l`（ubuntu / root）都是空，`/etc/cron.d/` 里只有 `s3-upload-sync`、`php`（session 清理）、`e2scrub_all`、`sysstat`。也就是说迁移后**系统定时任务和监控心跳都没恢复**：

- 采集当天（`timming.php` 里已开的 `collect_*`）不会自动跑
- 监控告警心跳不会上报

**2026-09-08 已补回 ubuntu 的 crontab**（走本机 80 + `Host` / `X-Origin-Proto` 头，绕开 CloudFront，避免 CDN 缓存和 301）：

```cron
* * * * * curl -fsS -m 55 -H "Host: 298965.com" -H "X-Origin-Proto: https" "http://127.0.0.1/api.php/timming/index" >/dev/null 2>&1
* * * * * curl -fsS -m 55 -H "Host: 298965.com" -H "X-Origin-Proto: https" "http://127.0.0.1/api.php/monitor/cron?token=<monitor.cron_token>" >/dev/null 2>&1
```

已验证 `journalctl -u cron` 每分钟触发、两个接口手工调用均返回正常（monitor 返回 `{"code":1,"msg":"ok",...}`，timming 逐条打印任务状态）。

若改走公网 `https://298965.com/...`，`https://` 和 `-L` 都不能省（80 会 301），且要注意 CloudFront 缓存。监控令牌在 `application/extra/maccms.php` 的 `monitor.cron_token`，后台重置令牌后必须同步改这行。

---

## 7. 发布

代码在 `/var/local/www/maccms10`，`origin` 指向主库、机器 SSH 到 GitHub 过不了 host key，所以更新用本机 **git bundle**，不要在服务器上 `git pull`。

本机（当前在 `self-operated-site`）：

```bash
OLD=$(ssh official 'git -C /var/local/www/maccms10 rev-parse HEAD')
git bundle create /tmp/maccms-self.bundle "$OLD"..self-operated-site
git bundle list-heads /tmp/maccms-self.bundle   # 必须列出 refs/heads/self-operated-site
scp /tmp/maccms-self.bundle official:/tmp/maccms-self.bundle
```

**range 右端必须写分支名，不能写 `HEAD`**：`"$OLD"..HEAD` 打出来的 bundle 里没有具名 ref，远端 `git fetch … self-operated-site:…` 会报 `fatal: couldn't find remote ref self-operated-site`（备份已做完、reset 还没跑，树是干净的，改好 bundle 重传即可续跑）。

**必须保留、不能被 reset 打回的文件**：

| 文件 | 原因 |
|------|------|
| `application/database.php` | 连库（现在指向 172.31.30.245） |
| `application/extra/maccms.php` | **真正生效的站点配置**（不再有 `/usr/local/etc` 那份挡着） |
| `application/route.php` | 后台「URL 地址配置」保存时重写，tracked 且线上改过 |
| `application/extra/bind.php` | 采集分类绑定 |
| `application/extra/mctheme.php` | 首页栏目 ID |
| `application/extra/timming.php` | 定时采集任务 |
| `application/extra/vodplayer.php` | 播放器 from |
| `static/js/playerconfig.js`、`static/js/tj.js` | 播放器 / 统计 |
| `static_new/images/touxiang.png` | 站长换过的默认头像（tracked） |
| `xyadmin.php` | 后台入口（`admin.php` / `install.php` 保持删除） |
| 未跟踪主题目录 | 见下，**禁止 rsync --delete 整棵 template** |

**主题的跟踪状态会变，别凭记忆。** 发布前现查：

```bash
ssh official 'cd /var/local/www/maccms10; for d in template/*/; do
  printf "%-16s tracked=%-4s engine=%-6s rss=%-2s map=%-2s verify=%s\n" "$(basename $d)" \
    "$(git ls-files "$d" | wc -l)" "$(grep -oP "engine\s*=\s*\K\w+" "$d/info.ini" 2>/dev/null || echo think)" \
    "$(ls "$d"html/rss/ 2>/dev/null | wc -l)" "$(ls "$d"html/map/ 2>/dev/null | wc -l)" "$(ls "$d"html/public/verify.* 2>/dev/null | wc -l)"
done'
```

2026-09-16 实测：`_shared` 880、`default` 785 是 tracked；`spike1` / `site-sidebar` 是 **tracked=0**（`spike` / `prism` / `site-np2` / `site-clean` / `spike-sidebar` 已不在盘上）。2026-10-09：PC `spike1`、手机 `spike2`，均 tracked=0——，`reset --hard` 不动它，但仍备份一份兜底。`tracked>0` 的主题 `reset --hard` **会**覆盖，要么确认 `git status --short template/<主题>/` 为空，要么先备份。

发布前先看 range 里有没有**删除 tracked 文件**的 commit：

```bash
git diff --name-status "$OLD"..self-operated-site | grep '^D' | cut -f2 | cut -d/ -f1-2 | sort -u
```

有的话 `reset --hard` 会连着删掉盘上被改过的文件（`ca659145` 一次删了 2438 个），要先 `cp -a` 整棵目录再还原。

### 发布前必须先过的两道闸（2026-09-08 实测踩到）

```bash
ssh official 'cd /var/local/www/maccms10
# ① ubuntu 能不能写 runtime/ —— 不能的话 git reset --hard 会失败
touch runtime/.wtest 2>/dev/null && rm runtime/.wtest && echo "runtime OK" || echo "runtime 不可写，见下"
# ② 备份目录能不能建
touch ~/.wtest && rm ~/.wtest && echo "HOME OK"'
```

- **`runtime/` 曾经属主是 `www-data`，导致 `git reset --hard` 写不回 `runtime/.gitignore`、`runtime/index.html` 两个 tracked 文件**（`Permission denied`，配 `set -e` 停在 reset 这一步，仓库半更新）。自 §1.1 把 fpm 改成 `ubuntu` 并 `chown -R ubuntu` 之后此问题消失；这道闸留着是为了万一有人又把属主改回去。修法：`sudo chown -R ubuntu:ubuntu runtime`。**不要** `sudo git reset`（整棵树会变 root 属主）。
- **`/var/local` 是 `root:staff` 775，ubuntu 不在 `staff` 组，写不进去**。备份目录必须放 `$HOME`（下面脚本已改），旧文档里的 `/var/local/backup-deploy-*` 会在第一条 `mkdir` 就失败。

远端步骤（**照抄前先跑上面的 tracked 查询**，把 `cp -a template/...` 换成当天真实存在的目录；写死目录名 + `set -e` = 发布中断在半路）：

```bash
ssh official 'set -e; cd /var/local/www/maccms10
BK=$HOME/backup-deploy-$(date +%Y%m%d-%H%M%S); echo "$BK" > /tmp/bk_path
mkdir -p "$BK/tracked/application/extra" "$BK/tracked/static/js" "$BK/tracked/static_new/images" "$BK/template"

# 1. 备份会被 reset 打回的 tracked 运行期文件
cp -a application/database.php            "$BK/tracked/application/"
cp -a application/route.php               "$BK/tracked/application/"
for f in timming vodplayer maccms bind mctheme; do cp -a application/extra/$f.php "$BK/tracked/application/extra/"; done
cp -a static/js/playerconfig.js           "$BK/tracked/static/js/"
cp -a static/js/tj.js                     "$BK/tracked/static/js/"
cp -a static_new/images/touxiang.png      "$BK/tracked/static_new/images/"
#    活主题目录（按当天实际存在的改）
cp -a template/spike1 template/site-sidebar "$BK/template/"
#    发布前状态，收尾要拿它对账
git status --short > "$BK/git-status-before.txt"; git rev-parse HEAD > "$BK/head-before.txt"

# 2. 更新
git fetch /tmp/maccms-self.bundle self-operated-site:refs/remotes/bundle/self-operated-site
git reset --hard refs/remotes/bundle/self-operated-site

# 3. 还原
cp -a "$BK/tracked/application/database.php" application/database.php
cp -a "$BK/tracked/application/route.php"    application/route.php
for f in timming vodplayer maccms bind mctheme; do cp -a "$BK/tracked/application/extra/$f.php" application/extra/$f.php; done
cp -a "$BK/tracked/static/js/playerconfig.js"      static/js/playerconfig.js
cp -a "$BK/tracked/static/js/tj.js"                static/js/tj.js
cp -a "$BK/tracked/static_new/images/touxiang.png" static_new/images/touxiang.png
#    活主题 tracked>0 且有线上改动时才需要回灌，如：cp -a "$BK/template/<主题>/." template/<主题>/
rm -f install.php admin.php

# 4. 清缓存（目录可能不全，逐个判断；直接 find 多个不存在的目录会 set -e 中断）
for d in runtime/cache runtime/temp runtime/twig; do [ -d "$d" ] && find "$d" -mindepth 1 -type f -delete; done; true'

ssh official 'sudo systemctl reload php7.0-fpm'
```

**收尾必须对账**，别只看站点能打开：

```bash
ssh official 'cd /var/local/www/maccms10; BK=$(cat /tmp/bk_path)
diff <(sort "$BK/git-status-before.txt") <(sort <(git status --short)) && echo "status 一致"
for f in application/database.php application/route.php application/extra/timming.php application/extra/vodplayer.php \
         application/extra/maccms.php application/extra/bind.php application/extra/mctheme.php \
         static/js/playerconfig.js static/js/tj.js static_new/images/touxiang.png; do
  cmp -s "$BK/tracked/$f" "$f" && echo "OK   $f" || echo "FAIL $f"
done
for d in spike1 site-sidebar; do
  echo "$d 差异=$(diff -rq "$BK/template/$d" "template/$d" 2>&1 | wc -l)"
done
ls -ld runtime runtime/cache upload   # 属主必须是 ubuntu（见 §1.1）'
```

**对账要紧接着还原跑，晚了会出现假 FAIL**：站点一恢复服务就往 `timming.php` 写 `runtime` 时间戳、往 `bind.php` 追加绑定。判断依据是**差异内容**不是 `cmp` 结果——只有 `runtime => <时间戳>` 变化 / `bind.php` 多绑定行、且 mtime 晚于备份，那是正常工作，**不要**拿备份盖回去。真正的还原失败长这样：mtime 停在 reset 那一刻、内容是仓库默认值（`timming.php` 全 `runtime => 0`、`bind.php` 空数组、`database.php` 连 demo 库）。

还原目录别写成 `[ -d template/x ] || cp -a …`：reset 只删 tracked 文件，目录里剩一张未跟踪图片目录就还在，判断会跳过还原，只剩残骸。

回滚：`git reset --hard $(cat $BK/head-before.txt)` 再重跑第 3 步；隔一段时间之后回滚时，`timming.php` / `bind.php` 要保留**现网当前**那份。

不要用发布去改 `template_dir`。主题在后台「系统 → 网站信息」选。

---

## 8. 现状速查（值都现查，别信文档里的数）

| 项 | 查法 |
|----|------|
| 发布到哪了 | `ssh official 'git -C /var/local/www/maccms10 log -1 --oneline'`（2026-10-09 为 `eb3e3824`） |
| 前台主题 | `grep template_dir application/extra/maccms.php`（PC / 手机常常不是同一套） |
| 服务状态 | `sudo systemctl status nginx php7.0-fpm` / `sudo ss -tlnp` |
| DB 状态 | `ssh official.db 'sudo docker ps'` |
| 后台 | `/xyadmin.php`（`admin.php` nginx 404）；账号 `admin`，密码在 official.db 的 `/var/local/.env` |
| 内容数量 | `select count(*) from mac_vod` 等 |

### 主题能力表（缺模板不是报错，是静默失败）

主题缺某个模板时，后台点生成没有产物、前台 200 但 body 为空。换主题或开验证码前对一遍（`spike1` 无 `html/rss/` → RSS/SiteMap 报「模板不存在」；`site-np2`/`spike-sidebar` 无 `public/verify` → 开验证码后分类页与搜索变成 200 + 空 body）。要给 `spike1` 补 RSS，可直接拿 `template/default/html/rss/*`（只依赖通用契约；`spike` 已从盘上删除），但 `spike1` 未跟踪，改前先备份。

Twig 主题（`info.ini` 里 `engine = twig`）的静态生成从 `126fd7a5` 起才支持。

### 内容/采集

| 表 | 说明 |
|----|------|
| `mac_vod` | 当天采集；播放地址都有 |
| `mac_art` | 量子文章接口，挂「小说」分类 |
| `mac_actor` | 量子演员，已开 |
| `mac_live` | CCTV，封面多为占位图 |
| `mac_manga` | 恒为 **0** —— 现有资源站没有 `provide/manga`，不是配置问题 |

首页 `mctheme.php` 栏目 ID 必须对上分类，否则接口有数据首页仍空：电影 20 / 电视剧 21 / 综艺 22 / 动漫 23（`type_mid=1`）、小说 68 / 资讯 69（`=2`）、漫画 70（`=12`，空）、演员 71（`=8`）。首页块 / 热门 / 导航 / 排行用 20–23；漫画、专题首页块已关（`hbtn=0`）。

采集出片顺序：分类 → 采集/自定义接口里**绑定**远端分类（写 `bind.php`）→ 点「采集当天」→ 开 `timming.php` 里的 `collect_*` **并且**恢复 §6 的 cron。首页是 AJAX（`/api.php/vod/get_latest_by_type`），分类 ID 对不上就整页骨架；轮播/热门还要求 `vod_level` ≥ 1。新浪视频接口 403，`collect_6` 已关。

---

## 9. 不要做的事

- 不要在 official 上找 docker / `docker compose`，也不要找 `/usr/local/etc/`、`ssl.conf`、certbot
- 不要把 PHP 连库改回 `127.0.0.1` —— DB 在 official.db（`172.31.30.245`）
- 不要把代码树 / `runtime/` / `upload/` chown 给 `www-data`，也不要把 fpm 改回 `www-data` 跑——后台所有写文件功能会全挂（见 §1.1）
- 不要把 MySQL root 口令写进代码；只放 official.db 的 `/var/local/.env`
- 不要在没确认安全组的情况下认为 Redis（无密码、`bind 0.0.0.0`）是安全的
- 不要在升级包或 rsync 里覆盖 `extra/maccms.php`、`timming.php`、`bind.php`、`mctheme.php`、`vodplayer.php`、`route.php`、`database.php`
- 不要用 rsync `--delete` 清掉 `template/` 下任何未跟踪主题（2026-09-16 是 `spike1`、`site-sidebar`；会变，现查）
- 不要凭记忆认定某棵主题「未跟踪所以 reset 不动」——先 `git ls-files template/<主题>/ | wc -l`
- 不要在没跑收尾对账的情况下宣布发布完成
- 不要把 `X-Forwarded-Proto` 当真（ALB 会覆盖成 http）——判断协议只认 `X-Origin-Proto`
- 不要 `sudo git reset` / 不要把备份目录放 `/var/local`（ubuntu 写不进去）——先过发布前那两道闸
- 不要把发布目标切回 `issue78`
- 不要对面板机 `ssh testing` 的路径下手
- 不要把 `.env` 原文贴到聊天

本机仓库的 `docker-compose.yml` 是开发测试用（相对路径、8000），和线上无关。
