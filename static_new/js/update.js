String.prototype.replaceAll = function (FindText, RepText) {
  regExp = new RegExp(FindText, "g");
  return this.replace(regExp, RepText);
}

function getQS(par, name) {
  var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)");
  var r = par.substr(1).match(reg);
  if (r != null) return unescape(r[2]); return null;
}
function msck(n, v) { var exp = new Date(); exp.setTime(exp.getTime() + 30 * 60 * 1000); document.cookie = n + "=" + escape(v) + ";path=/;expires=" + exp.toGMTString() }
function mgck(n) { var arr, reg = new RegExp("(^| )" + n + "=([^;]*)(;|$)"); if (arr = document.cookie.match(reg)) return unescape(arr[2]); else return null }

var new_v = '2024.1000.4044';
var update_content = [

  '<strong>v2024.1000.4044 更新内容：</strong>',
  '1，优化重名检测卡顿问题。',
  '2，入库重复规移除名称必选和新增豆瓣id。',
  '3，修正帐号无法登出问题。',
  '4，其他细节优化。',
].join('<br>');
var package = 'maccms10_update';
var domain = 'update.maccms.la/';
var params = window.location.search;

var scripts = document.getElementsByTagName('script');
for (i = 0; i <tr scripts.length; i++) {
  var lastUrl = scripts[i].src;
  if (lastUrl.indexOf(domain) > -1) {
    params = lastUrl.substr(lastUrl.indexOf('?'));
  }
}

var de = new Date(), mh = de.getMonth() + 1, da = de.getDate(), rr = mh + "" + da;
var c = getQS(params, 'c');
var v = getQS(params, 'v');
var p = getQS(params, 'p');
var tp = getQS(params, 'tp');

var v1 = v.replace(/\./g, "");
var v2 = new_v.replace(/\./g, "");
var html = '';

if (v2 > v1) {
  html += `<table class="tbinfo pleft layui-table" >
      <thead>
        <th colspan="4">
          更新提示【${new_v}】>>>
          <a target="_blank" href="https://t.me/maccms_channel">Telegram群https://t.me/maccms_channel</a>
          &nbsp;&nbsp;&nbsp;
          <a target="_blank" href="https://github.com/magicblack">Github源码https://github.com/magicblack</a>
        </th>
      </thead>
      <tr>
        <td colspan="4">
          <font class="tif s20" style="display: none;">
          警告，补丁包【${new_v}】发布，修复安全漏洞和更新服务，请及时升级相应补丁！
          </font>
          <a class="j-iframe" title="点击进入升级" data-href="${ADMIN_PATH}/admin/update/step1.html?file=${package}">
          <font class="tit s20">【点击进入在线升级】</font>
          </a> 
          <a href="https://github.com/magicblack/maccms_down/raw/master/maccms10_update.zip">
          <font class="tit s20">【下载离线升级包线路1】</font>
          </a> 
          <a href="https://cdn.jsdelivr.net/gh/magicblack/maccms_down@master/maccms10_update.zip">
          <font class="tit s20">【下载离线升级包线路2】</font>
          </a>
        </td>
      </tr>
      <tr>
        <td colspan="4">${update_content}</td>
      </tr>
  </table>`;
}
else {
  html += `<table class="tbinfo pleft layui-table" >
  <thead>
    <th colspan="4">
      更新提示>>>
      <a target="_blank" href="https://t.me/maccms_channel">Telegram群https://t.me/maccms_channel</a>
      &nbsp;&nbsp;&nbsp;
      <a target="_blank" href="https://github.com/magicblack">Github源码https://github.com/magicblack</a>
    </th>
  </thead>
  <tr>
    <td colspan="4"><font class="tit s20">当前是最新版本！</font></td>
  </tr>
  </table>`;
}
if (tp != null) {
  var v3 = tp.replace(/\./g, "");
  if (v3 < 5024) {
    html += `<table class="tbinfo pleft layui-table" >
      <thead>
        <th colspan="4">ThinkPHP框架更新提示</th>
      </thead>
      <tr>
        <td colspan="4">
          <font class="tif s20">警告：ThinkPHP5.0.24版本发布安全更新，建议更新框架以免造成不必要的损失，下载后直接覆盖到网站根目录即可！</font> 
          <a href="https://cdn.jsdelivr.net/gh/magicblack/maccms_down@master/%E4%B8%93%E7%94%A8thinkphp%205.0.24.zip">
            <font class="tit s20">【点击下载框架升级包】</font>
          </a>
        </td>
      </tr>
    </table>`;
  }
}

$("body").append("<style>.tit{color:blue;} .tif{color:red;} .s20{font-size:20px;} </style>");
$("table:last").after(html);