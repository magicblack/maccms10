# Actor 接口文档
### 名词解释：PHP_INT_MAX一般为9223372036854775807
## 1. get_list 接口

### 请求方式
- **GET**

### URL
- `/api.php/actor/get_list/`

### 参数说明
| 参数名 | 必填 | 类型 | 说明 |
| --- | --- | --- | --- |
| offset | 否 | number | 偏移量，数值范围：[1, PHP_INT_MAX] |
| limit | 否 | number | 获取数量，数值范围：[1, PHP_INT_MAX] |
| id | 否 | number | ID，数值范围：[1, PHP_INT_MAX] |
| type_id | 否 | number | 类型ID，数值范围：[1, 100] |
| sex | 否 | string | 性别，可选值："男", "女" |
| area | 否 | string | 地区，最大长度255字符 |
| letter | 否 | string | 字母，最大长度1字符 |
| level | 否 | string | 级别，最大长度1字符 |
| name | 否 | string | 名字，最大长度20字符 |
| blood | 否 | string | 血型，最大长度10字符 |
| starsign | 否 | string | 星座，最大长度255字符 |
| orderby | 否 | string | 排序字段，可选值："hits", "hits_month", "hits_week", "hits_day", "time" |

## 2. get_detail 接口

### 请求方式
- **GET**

### URL
- `/api.php/actor/get_detail/`

### 参数说明
| 参数名 | 必填 | 类型 | 说明 |
| --- | --- | --- | --- |
| actor_id | 是 | number | 演员ID，数值范围：[1, PHP_INT_MAX] |

# Art 接口文档

## 一、获取列表信息 (`get_list`)

### 请求方式
- `GET`

### URL
- `/api.php/art/get_list/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| offset | number | 否 | 偏移量，默认为0，数值范围：[0, PHP_INT_MAX] |
| limit | number | 否 | 返回数据条数，默认值及最大值为500，最小值为1 |
| tag | string | 否 | 标签，最大长度为100字符 |
| orderby | string | 否 | 排序字段，可选值：id,time,time_add,score,hits,hits_day,hits_week,hits_month,up,down,level |
| letter | string | 否 | 字母，最大长度为1字符 |
| status | number | 否 | 状态，数值范围：[1,10] |
| name | string | 否 | 名称，最大长度为100字符 |
| sub | string | 否 | 子标题，最大长度为100字符 |
| blurb | string | 否 | 摘要，最大长度为100字符 |
| title | string | 否 | 标题，最大长度为50字符 |
| content | string | 否 | 内容，最大长度为100字符 |
| time_start | number | 否 | 开始时间戳，数值范围：[1, PHP_INT_MAX] |
| time_end | number | 否 | 结束时间戳，数值范围：[1, PHP_INT_MAX] |

## 二、获取详情信息 (`get_detail`)

### 请求方式
- `GET`

### URL
- `/api.php/art/get_detail/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| art_id | number | 是 | 艺术作品ID，数值范围：[0, PHP_INT_MAX] |

# Comment 接口文档

## 一、获取评论列表 (`get_list`)

### 请求方式
- `GET`

### URL
- `/api.php/comment/get_list/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| offset | number | 是 | 偏移量，数值范围：[1, PHP_INT_MAX] |
| limit | number | 是 | 返回数据条数，数值范围：[1, PHP_INT_MAX] |
| rid | number | 是 | 相关ID（例如文章或作品的ID），数值范围：[1, PHP_INT_MAX] |
| orderby | string | 否 | 排序字段，可选值：time, up, down |

# Gbook 接口文档

## 一、获取列表信息 (`get_list`)

### 请求方式
- `GET`

### URL
- `/api.php/gbook/get_list/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| offset | number | 否 | 偏移量，默认为0，数值范围：[0, PHP_INT_MAX] |
| limit | number | 否 | 返回数据条数，默认值及最大值为500，最小值为1 |
| id | number | 否 | 记录ID，数值范围：[1, PHP_INT_MAX] |
| rid | number | 否 | 回复ID，数值范围：[1, PHP_INT_MAX] |
| user_id | number | 否 | 用户ID，数值范围：[1, PHP_INT_MAX] |
| status | number | 否 | 状态，数值范围：[0, 10] |
| name | string | 否 | 名称，最大长度为20字符 |
| orderby | string | 否 | 排序字段，可选值：id,time,reply_time |

# Link 接口文档

## 一、获取列表信息 (`get_list`)

### 请求方式
- `GET`

### URL
- `/api.php/link/get_list/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| id | number | 否 | ID，数值范围：[1, PHP_INT_MAX] |
| type | number | 否 | 类型，数值范围：[1, PHP_INT_MAX] |
| name | string | 否 | 名称，最大长度为100字符 |
| sort | number | 否 | 排序值，数值范围：[1, PHP_INT_MAX] |
| time_start | number | 否 | 开始时间戳，数值范围：[1, PHP_INT_MAX] |
| time_end | number | 否 | 结束时间戳，数值范围：[1, PHP_INT_MAX] |
| orderby | string | 否 | 排序字段，可选值：id,time,time_add |

# Topic 接口文档

## 一、获取话题列表 (`get_list`)

### 请求方式
- `GET`

### URL
- `/api.php/topic/get_list/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| offset | number | 否 | 偏移量，默认为0，数值范围：[0, PHP_INT_MAX] |
| limit | number | 否 | 返回数据条数，默认值及最大值为500，最小值为1 |
| orderby | string | 否 | 排序字段，可选值：id,time,time_add,score,hits,hits_day,hits_week,hits_month,up,down,level |
| time_start | number | 否 | 开始时间戳，数值范围：[0, PHP_INT_MAX] |
| time_end | number | 否 | 结束时间戳，数值范围：[0, PHP_INT_MAX] |

---

## 二、获取话题详情 (`get_detail`)

### 请求方式
- `GET`

### URL
- `/api.php/topic/get_detail/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| topic_id | number | 是 | 话题ID，数值范围：[0, PHP_INT_MAX] |

# Type 接口文档

## 一、获取类型列表 (`get_list`)

### 请求方式
- `GET`

### URL
- `/api.php/type/get_list/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| type_id | number | 是 | 类型ID，数值范围：[1, PHP_INT_MAX] |

# User 接口文档

## 一、获取用户列表 (`get_list`)

### 请求方式
- `GET`

### URL
- `/api.php/user/get_list/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| offset | number | 否 | 偏移量，默认为0，数值范围：[0, PHP_INT_MAX] |
| limit | number | 否 | 返回数据条数，默认值及最大值为500，最小值为1 |
| name | string | 否 | 用户名，最大长度为50字符 |
| nickname | string | 否 | 昵称，最大长度为50字符 |
| email | string | 否 | 邮箱地址，最大长度为100字符 |
| qq | string | 否 | QQ号，最大长度为20字符 |
| phone | string | 否 | 手机号，最大长度为20字符 |
| reg_time_start | number | 否 | 注册开始时间戳，数值范围：[1, PHP_INT_MAX] |
| reg_time_end | number | 否 | 注册结束时间戳，数值范围：[1, PHP_INT_MAX] |
| group_id | number | 否 | 用户组ID，数值范围：[1, 500] |

---

## 二、获取用户详情 (`get_detail`)

### 请求方式
- `GET`

### URL
- `/api.php/user/get_detail/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| id | number | 是 | 用户ID，数值范围：[1, PHP_INT_MAX] |

# Vod 接口文档

## 一、获取视频列表 (`get_list`)

### 请求方式
- `GET`

### URL
- `/api.php/vod/get_list/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| id | number | 否 | 视频ID，数值范围：[0, PHP_INT_MAX] |
| offset | number | 否 | 偏移量，默认为0，数值范围：[0, PHP_INT_MAX] |
| limit | number | 否 | 返回数据条数，默认值及最大值为500，最小值为1 |
| orderby | string | 否 | 排序字段，可选值：hits, up, pubdate, hits_week, hits_month, hits_day, score |
| type_id | number | 否 | 类别ID，数值范围：[0, PHP_INT_MAX] |
| vod_letter | string | 否 | 字母，最大长度为1字符 |
| vod_name | string | 否 | 视频名称，最大长度为50字符 |
| vod_tag | string | 否 | 标签，最大长度为20字符 |
| vod_blurb | string | 否 | 摘要，最大长度为20字符 |
| vod_class | string | 否 | 分类，最大长度为10字符 |

---

## 二、获取视频详情 (`get_detail`)

### 请求方式
- `GET`

### URL
- `/api.php/vod/get_detail/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| vod_id | number | 是 | 视频ID，数值范围：[0, PHP_INT_MAX] |

---

## 三、获取按年份分类的视频 (`get_year`)

### 请求方式
- `GET`

### URL
- `/api.php/vod/get_year/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| type_id_1 | number | 是 | 年份类别ID，数值范围：[0, PHP_INT_MAX] |

---

## 四、获取按分类的视频 (`get_class`)

### 请求方式
- `GET`

### URL
- `/api.php/vod/get_class/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| type_id_1 | number | 是 | 分类ID，数值范围：[0, PHP_INT_MAX] |

---

## 五、获取按地区的视频 (`get_area`)

### 请求方式
- `GET`

### URL
- `/api.php/vod/get_area/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| type_id_1 | number | 是 | 地区ID，数值范围：[0, PHP_INT_MAX] |

# Website 接口文档

## 一、获取网站列表 (`get_list`)

### 请求方式
- `GET`

### URL
- `/api.php/website/get_list/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| offset | number | 否 | 偏移量，默认为0，数值范围：[0, PHP_INT_MAX] |
| limit | number | 否 | 返回数据条数，默认值及最大值为500，最小值为1 |
| type_id | number | 否 | 类别ID，数值范围：[1, 100] |
| name | string | 否 | 网站名称，最大长度为20字符 |
| sub | string | 否 | 子标题，最大长度为20字符 |
| en | string | 否 | 英文名称，最大长度为20字符 |
| status | number | 否 | 状态，数值范围：[1, 9] |
| letter | string | 否 | 字母，最大长度为1字符 |
| area | string | 否 | 地区，最大长度为10字符 |
| lang | string | 否 | 语言，最大长度为10字符 |
| level | number | 否 | 级别，数值范围：[1, 9] |
| start_time | number | 否 | 开始时间戳，数值范围：[1, PHP_INT_MAX] |
| end_time | number | 否 | 结束时间戳，数值范围：[1, PHP_INT_MAX] |
| tag | string | 否 | 标签，最大长度为20字符 |
| orderby | string | 否 | 排序字段，可选值：id, time, time_add, score, hits, up, down, level |

---

## 二、获取网站详情 (`get_detail`)

### 请求方式
- `GET`

### URL
- `/api.php/website/get_detail/`

### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
| --- | --- | --- | --- |
| website_id | number | 是 | 网站ID，数值范围：[1, PHP_INT_MAX] |