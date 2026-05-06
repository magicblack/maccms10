# 数据库管理、执行 SQL、批量替换、挂马检测

## 数据库管理 (`数据库 → 数据库管理` / `menu/database`)

- **备份**：生成 `.sql` 或分包（备份目录见 `maccms.db.backup_path`，默认 `./application/data/backup/database/`）。  
- **恢复**：覆盖当前数据——**只对测试库或停机维护窗口**执行。  
- **优化/修复表**：InnoDB 碎片或异常关机后可尝试（仍先备份）。

## 执行 SQL 语句 (`database_sql`)

- **最高危**：一句 `DROP`/`DELETE`/错误 `UPDATE` 可毁掉全站。  
- 守则：**先备份 → 仅 SELECT 试运行 → 低峰窗口 → 由 DBA执行**。  
- 禁止在生产让 **不受信管理员**有此权限。

## 数据批量替换 (`database_rep`)

- 批量替换字段内字符串（如域名迁移、违禁词）；必须 **限定表与 WHERE**（若有），**先抽样 SELECT**。  
- **UTF-8 / 二进制**混淆可能乱码。

## 挂马检测 (`database_inspect` / `menu/database_inspect` 与安全菜单)

- 扫描 **`mac_*` 表**中可疑 iframe/script/Base64（**启发式**，非杀毒软件）。  
- 发现后：**人工核验 → 溯源入侵（弱口令/FTP/WebShell）→ 修漏洞 → 全量杀毒**。

## English summary

**Backup/restore** live under Database menu. **Ad-hoc SQL** and **batch replace** are destructive by nature—mandatory backups and staging first. **Inspect** finds obvious malware patterns, not guarantees.
