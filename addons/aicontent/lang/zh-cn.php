<?php

return [
    // General
    'AI Content Assistant'                  => 'AI内容助理',
    'AI Content Assistant — Configuration'  => 'AI内容助理 — 配置',
    'AI Content Assistant - Dashboard'      => 'AI内容助理 - 控制台',
    'AI Content Assistant - Generate Content' => 'AI内容助理 - 生成内容',
    'Generate Content'                      => '生成内容',
    'New Generation'                        => '新建生成',
    'Task History'                          => '任务历史',
    'Configuration'                         => '插件配置',
    'Save Configuration'                    => '保存配置',
    'Back'                                  => '返回',
    'Delete'                                => '删除',
    'View'                                  => '查看',
    'Test'                                  => '测试',
    'Test Key'                              => '测试密钥',
    'Open Plugin'                           => '打开插件',
    'Default Settings'                      => '默认设置',

    // Status
    'Pending'   => '等待中',
    'Done'      => '已完成',
    'Error'     => '出错',
    'Unknown'   => '未知',
    'Total'     => '总计',
    'Completed' => '已完成',
    'Errors'    => '出错',

    // Stats / table headers
    'Total Tasks'      => '总任务数',
    'ID'               => 'ID',
    'Content'          => '内容',
    'Type'             => '类型',
    'Provider / Model' => '提供商 / 模型',
    'Status'           => '状态',
    'Created'          => '创建时间',
    'Actions'          => '操作',
    'Provider'         => '提供商',

    // Content types
    'Video'   => '视频',
    'Article' => '文章',
    'Topic'   => '话题',

    // Pagination
    'Prev' => '上一页',
    'Next' => '下一页',
    'Page' => '第',

    // Form labels
    'Content Type'   => '内容类型',
    'Title'          => '标题',
    'Genre / Type'   => '类型 / 分类',
    'Year'           => '年份',
    'Area / Country' => '地区',
    'Actors'         => '主演',
    'Director'       => '导演',
    'AI Provider'    => 'AI 提供商',
    'Model'          => '模型',
    'Output Language'=> '输出语言',
    'Max Tokens'     => '最大Token数',
    'Batch Size'     => '批量大小',
    'Timeout (s)'    => '超时（秒）',
    'API Key'        => 'API密钥',

    // Placeholders
    'Video or article title'          => '视频或文章标题',
    'e.g. Action, Romance, Documentary' => '如：动作、爱情、纪录片',
    'e.g. 2024'                       => '如：2024',
    'e.g. China, USA, Japan'          => '如：中国、美国、日本',
    'Main cast, comma-separated'      => '主演，逗号分隔',
    'Director name'                   => '导演姓名',

    // Result / view labels
    'SEO Title'        => 'SEO标题',
    'Description'      => '简介描述',
    'Tags'             => '标签',
    'Copy All'         => '复制全部',
    'Generated Content'=> '生成的内容',
    'Raw AI Response'  => 'AI原始响应',
    'Task Result'      => '任务结果',
    'Task ID'          => '任务ID',
    'Generate Content with AI' => '使用AI生成内容',

    // Config page help text
    'Only the key for your selected provider is required. Other fields are hidden.'
        => '只需填写所选提供商的密钥，其他字段已隐藏。',
    'Select provider, then choose a model from the right dropdown.'
        => '选择提供商后，从右侧下拉菜单选择模型。',
    'Recommended: 800–2000.' => '建议：800–2000。',

    // config.php field titles
    'Model Name'   => '模型名称',
    'Language'     => '语言',

    // config.php tips
    'Default AI provider for content generation.'   => '默认AI提供商，用于生成内容。',
    'Claude: claude-sonnet-4-6 | OpenAI: gpt-4o | Gemini: gemini-2.0-flash | DeepSeek: deepseek-chat | Qwen: qwen-turbo | GLM: glm-4'
        => 'Claude: claude-sonnet-4-6 | OpenAI: gpt-4o | Gemini: gemini-2.0-flash | DeepSeek: deepseek-chat | Qwen: qwen-turbo | GLM: glm-4',
    'Anthropic API key (console.anthropic.com)'     => 'Anthropic API密钥 (console.anthropic.com)',
    'OpenAI API key (platform.openai.com)'          => 'OpenAI API密钥 (platform.openai.com)',
    'Google AI Studio API key (aistudio.google.com)'=> 'Google AI Studio API密钥 (aistudio.google.com)',
    'DeepSeek platform API key (platform.deepseek.com)' => 'DeepSeek平台API密钥 (platform.deepseek.com)',
    'Alibaba DashScope API key (dashscope.console.aliyun.com)' => '阿里云DashScope API密钥 (dashscope.console.aliyun.com)',
    'Zhipu AI API key (open.bigmodel.cn)'           => '智谱AI API密钥 (open.bigmodel.cn)',
    'Max tokens in AI response. Recommended: 800-2000.' => 'AI响应的最大Token数，建议：800-2000。',
    'Items per batch job.'                          => '每批次处理的条目数量。',
    'HTTP timeout in seconds for AI API calls.'     => 'AI API调用的超时时间（秒）。',
    'Language for AI generated content.'            => 'AI生成内容的输出语言。',

    // API key labels
    'Claude Key'   => 'Claude 密钥',
    'OpenAI Key'   => 'OpenAI 密钥',
    'Gemini Key'   => 'Gemini 密钥',
    'DeepSeek Key' => 'DeepSeek 密钥',
    'Qwen Key'     => 'Qwen 密钥',
    'GLM Key'      => 'GLM 密钥',

    // Providers
    'Claude (Anthropic)' => 'Claude (Anthropic)',
    'OpenAI (GPT)'       => 'OpenAI (GPT)',
    'Google Gemini'      => 'Google Gemini',
    'DeepSeek'           => 'DeepSeek',
    'Alibaba Qwen'       => '阿里云通义千问',
    'Zhipu GLM'          => '智谱AI',

    // Language options (Output Language select)
    'Chinese Simplified (简体中文)'  => '简体中文',
    'Chinese Traditional (繁體中文)' => '繁體中文',
    'English'                        => 'English',
    'Korean (한국어)'                 => '한국어',
    'Japanese (日本語)'               => '日本語',
    'German (Deutsch)'               => 'Deutsch',
    'French (Français)'              => 'Français',
    'Spanish (Español)'              => 'Español',
    'Portuguese (Português)'         => 'Português',

    // Controller messages
    'Unauthorized. Please log in to the admin panel.' => '未授权，请登录后台管理。',
    'Please log in first.'            => '请先登录。',
    'Invalid request token.'          => '无效的请求令牌。',
    'Rate limit exceeded. Please wait a moment.' => '请求过于频繁，请稍后再试。',
    'Content generated successfully.' => '内容生成成功。',
    'Content generation failed. Please check your AI provider configuration.'
        => '内容生成失败，请检查AI提供商配置。',
    'Failed to load content records.' => '加载内容记录失败。',
    'Generation failed for this item.'=> '此条目生成失败。',
    'Processed %d items, %d succeeded.' => '已处理 %d 条，成功 %d 条。',
    'Please write something first before enhancing.' => '请先输入内容，再点击增强。',
    'Enhanced successfully.'          => '增强成功。',
    'Enhancement failed. Please check your AI provider configuration.'
        => '增强失败，请检查AI提供商配置。',
    'Provider is required.'           => '请选择AI提供商。',
    'Connection successful.'          => '连接成功。',
    'Connection failed.'              => '连接失败。',
    'Connection test failed. Please verify your API key.' => '连接测试失败，请验证您的API密钥。',
    'A database error occurred. Please try again later.' => '数据库错误，请稍后再试。',
    'An unexpected error occurred. Please try again.' => '发生意外错误，请重试。',
    'Title is required for content generation.' => '生成内容需要填写标题。',
    'API key for provider is not configured.'   => '该AI提供商的API密钥尚未配置。',
    'No content IDs provided.'        => '未提供内容ID。',
    'Task not found'                  => '任务不存在。',
    'Deleted successfully'            => '删除成功。',

    // UI confirm / misc
    'No tasks yet. Click "New Generation" to get started.' => '暂无任务。点击"新建生成"开始使用。',
    'Delete this task record?'        => '确定删除此任务记录？',
    'Please enter the API key first.' => '请先输入API密钥。',

    // JS strings (injected via window.AI_LANG)
    'Write something in this field first, then click AI to enhance it.'
        => '请先在此处输入内容，再点击AI进行增强。',
    'Enhanced!'         => '增强成功！',
    'Enhancement failed.'  => '增强失败。',
    'Enhance with AI'   => '使用AI增强',
    'Enter the API key first.' => '请先输入API密钥。',
    'Testing...'        => '测试中...',
    'Generating...'     => '生成中...',
    'Generation failed.'=> '生成失败。',
    'Copied!'           => '已复制！',
    'Failed'            => '失败',
    'SEO Title: '       => 'SEO标题：',
    'Description: '     => '简介：',
    'Tags: '            => '标签：',
    '✓ OK'              => '✓ 成功',
    '✗ Fail'            => '✗ 失败',
    '✓ Connected'       => '✓ 已连接',
];
