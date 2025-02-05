# 数据分析平台

一个用于采集和分析数据的 Web 平台，支持多用户管理、数据采集、数据分析和可视化展示。

## 功能特点

- 多用户系统，支持管理员和普通用户角色
- 自动数据采集，支持定时更新
- 数据可视化展示，包括趋势图、饼图等
- UID 管理，支持备注名称
- 灵活的价格设置系统
- 响应式设计，支持移动端访问

## 系统要求

- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- Web 服务器（Apache/Nginx）
- 启用 PDO 和 PDO_MySQL 扩展
- 启用 cURL 扩展

## 安装步骤

1. 将代码部署到 Web 服务器目录
2. 确保 `config`、`storage` 目录可写
3. 访问网站，系统将自动跳转到初始化页面
4. 按照提示配置数据库信息
5. 创建管理员账号
6. 完成安装

## 目录结构 
├── config/ # 配置文件
├── cron/ # 定时任务脚本
├── includes/ # 核心类和函数
├── public/ # 公共访问目录
│ ├── css/ # 样式文件
│ ├── images/ # 图片资源
│ └── index.php # 入口文件
├── storage/ # 存储目录
│ ├── cookies/ # Cookie 文件
│ └── logs/ # 日志文件
└── templates/ # 模板文件
├── admin/ # 管理员模板
└── layout/ # 布局模板

## 定时任务配置

添加以下 crontab 配置以启用自动更新：

## 安全建议

1. 确保 `config` 目录不可通过 Web 访问
2. 定期备份数据库
3. 使用强密码
4. 及时更新 PHP 和相关扩展
5. 配置 SSL 证书，使用 HTTPS

## 使用说明

1. 登录系统
2. 在"数据采集"页面添加 UID 和数据源
3. 设置自动更新时间（可选）
4. 在"数据分析"页面查看统计和图表
5. 使用"UID管理"功能添加备注名称
6. 管理员可以在"用户管理"中管理其他用户

## 常见问题

1. 数据采集失败
   - 检查 URL 是否正确
   - 确认 cURL 扩展已启用
   - 查看错误日志

2. 自动更新不工作
   - 检查 crontab 配置
   - 确认 PHP CLI 可用
   - 检查文件权限

3. 图表不显示
   - 确认有数据记录
   - 检查浏览器控制台错误
   - 清除浏览器缓存

## 更新日志

### v1.0.0 (2024-02-04)
- 初始版本发布
- 基础功能实现
- 响应式界面设计

## 许可证

MIT License

## 联系方式

如有问题或建议，请提交 Issue 或联系管理员。