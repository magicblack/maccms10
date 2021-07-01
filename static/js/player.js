var killErrors=function(value){return true};window.onerror=null;window.onerror=killErrors;
var base64EncodeChars="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";var base64DecodeChars=new Array(-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,-1,62,-1,-1,-1,63,52,53,54,55,56,57,58,59,60,61,-1,-1,-1,-1,-1,-1,-1,0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,-1,-1,-1,-1,-1,-1,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,-1,-1,-1,-1,-1);function base64encode(str){var out,i,len;var c1,c2,c3;len=str.length;i=0;out="";while(i<len){c1=str.charCodeAt(i++)&0xff;if(i==len){out+=base64EncodeChars.charAt(c1>>2);out+=base64EncodeChars.charAt((c1&0x3)<<4);out+="==";break}c2=str.charCodeAt(i++);if(i==len){out+=base64EncodeChars.charAt(c1>>2);out+=base64EncodeChars.charAt(((c1&0x3)<<4)|((c2&0xF0)>>4));out+=base64EncodeChars.charAt((c2&0xF)<<2);out+="=";break}c3=str.charCodeAt(i++);out+=base64EncodeChars.charAt(c1>>2);out+=base64EncodeChars.charAt(((c1&0x3)<<4)|((c2&0xF0)>>4));out+=base64EncodeChars.charAt(((c2&0xF)<<2)|((c3&0xC0)>>6));out+=base64EncodeChars.charAt(c3&0x3F)}return out}function base64decode(str){var c1,c2,c3,c4;var i,len,out;len=str.length;i=0;out="";while(i<len){do{c1=base64DecodeChars[str.charCodeAt(i++)&0xff]}while(i<len&&c1==-1);if(c1==-1)break;do{c2=base64DecodeChars[str.charCodeAt(i++)&0xff]}while(i<len&&c2==-1);if(c2==-1)break;out+=String.fromCharCode((c1<<2)|((c2&0x30)>>4));do{c3=str.charCodeAt(i++)&0xff;if(c3==61)return out;c3=base64DecodeChars[c3]}while(i<len&&c3==-1);if(c3==-1)break;out+=String.fromCharCode(((c2&0XF)<<4)|((c3&0x3C)>>2));do{c4=str.charCodeAt(i++)&0xff;if(c4==61)return out;c4=base64DecodeChars[c4]}while(i<len&&c4==-1);if(c4==-1)break;out+=String.fromCharCode(((c3&0x03)<<6)|c4)}return out}function utf16to8(str){var out,i,len,c;out="";len=str.length;for(i=0;i<len;i++){c=str.charCodeAt(i);if((c>=0x0001)&&(c<=0x007F)){out+=str.charAt(i)}else if(c>0x07FF){out+=String.fromCharCode(0xE0|((c>>12)&0x0F));out+=String.fromCharCode(0x80|((c>>6)&0x3F));out+=String.fromCharCode(0x80|((c>>0)&0x3F))}else{out+=String.fromCharCode(0xC0|((c>>6)&0x1F));out+=String.fromCharCode(0x80|((c>>0)&0x3F))}}return out}function utf8to16(str){var out,i,len,c;var char2,char3;out="";len=str.length;i=0;while(i<len){c=str.charCodeAt(i++);switch(c>>4){case 0:case 1:case 2:case 3:case 4:case 5:case 6:case 7:out+=str.charAt(i-1);break;case 12:case 13:char2=str.charCodeAt(i++);out+=String.fromCharCode(((c&0x1F)<<6)|(char2&0x3F));break;case 14:char2=str.charCodeAt(i++);char3=str.charCodeAt(i++);out+=String.fromCharCode(((c&0x0F)<<12)|((char2&0x3F)<<6)|((char3&0x3F)<<0));break}}return out}

var MacPlayer = {
'GetDate':function(f,t){
    if(!t){
        t = new Date();
    }
    var Week = ['日', '一', '二', '三', '四', '五', '六'];
    f = f.replace(/yyyy|YYYY/, t.getFullYear());
    f = f.replace(/yy|YY/, (t.getYear() % 100) > 9 ? (t.getYear() % 100).toString() : '0' + (t.getYear() % 100));
    f = f.replace(/MM/, t.getMonth() > 9 ? t.getMonth().toString() : '0' + t.getMonth());
    f = f.replace(/M/g, t.getMonth());
    f = f.replace(/w|W/g, Week[t.getDay()]);
    f = f.replace(/dd|DD/, t.getDate() > 9 ? t.getDate().toString() : '0' + t.getDate());
    f = f.replace(/d|D/g, t.getDate());
    f = f.replace(/hh|HH/, t.getHours() > 9 ? t.getHours().toString() : '0' + t.getHours());
    f = f.replace(/h|H/g, t.getHours());
    f = f.replace(/mm/, t.getMinutes() > 9 ? t.getMinutes().toString() : '0' + t.getMinutes());
    f = f.replace(/m/g, t.getMinutes());
    f = f.replace(/ss|SS/, t.getSeconds() > 9 ? t.getSeconds().toString() : '0' + t.getSeconds());
    f = f.replace(/s|S/g, t.getSeconds());
    return f;
},
'GetUrl': function(s, n) {
    return this.Link.replace('{sid}', s).replace('{sid}', s).replace('{nid}', n).replace('{nid}', n)
},
'Go': function(s, n) {
    location.href = this.GetUrl(s, n)
},
'Show': function() {
    $('#buffer').attr('src', this.Prestrain);
    setTimeout(function() {
        MacPlayer.AdsEnd()
    }, this.Second * 1000);
    $("#playleft").get(0).innerHTML = this.Html + '';
},
'AdsStart': function() {
    if ($("#buffer").attr('src') != this.Buffer) {
        $("#buffer").attr('src', this.Buffer)
    }
    $("#buffer").show()
},
'AdsEnd': function() {
    $('#buffer').hide()
},
'Install': function() {
    this.Status = false;
    $('#install').show()
},
'Play': function() {
    document.write('<style>.MacPlayer{background: #000000;font-size:14px;color:#F6F6F6;margin:0px;padding:0px;position:relative;overflow:hidden;width:' + this.Width + ';height:' + this.Height + ';min-height:100px;}.MacPlayer table{width:100%;height:100%;}.MacPlayer #playleft{position:inherit;!important;width:100%;height:100%;}</style><div class="MacPlayer">' + '<iframe id="buffer" src="" frameBorder="0" scrolling="no" width="100%" height="100%" style="position:absolute;z-index:99998;"></iframe><iframe id="install" src="" frameBorder="0" scrolling="no" width="100%" height="100%" style="position:absolute;z-index:99998;display:none;"></iframe>' + '<table border="0" cellpadding="0" cellspacing="0"><tr><td id="playleft" valign="top" style="">&nbsp;</td></table></div>');
    this.offsetHeight = $('.MacPlayer').get(0).offsetHeight;
    this.offsetWidth = $('.MacPlayer').get(0).offsetWidth;
    document.write('<scr' + 'ipt src="' + this.Path + this.PlayFrom + '.js"></scr' + 'ipt>')
},
'Down': function() {},
'Init': function() {
    this.Status = true;
    this.Parse = '';
    var player_data = player_aaaa;
    if (player_data.encrypt == '1') {
        player_data.url = unescape(player_data.url);
        player_data.url_next = unescape(player_data.url_next)
    } else if (player_data.encrypt == '2') {
        player_data.url = unescape(base64decode(player_data.url));
        player_data.url_next = unescape(base64decode(player_data.url_next))
    }
    this.Agent = navigator.userAgent.toLowerCase();
    this.Width = MacPlayerConfig.width;
    this.Height = MacPlayerConfig.height;
    if (this.Agent.indexOf("android") > 0 || this.Agent.indexOf("mobile") > 0 || this.Agent.indexOf("ipod") > 0 || this.Agent.indexOf("ios") > 0 || this.Agent.indexOf("iphone") > 0 || this.Agent.indexOf("ipad") > 0) {
        this.Width = MacPlayerConfig.widthmob;
        this.Height = MacPlayerConfig.heightmob
    }
    if (this.Width.indexOf("px") == -1 && this.Width.indexOf("%") == -1) {
        this.Width = '100%'
    }
    if (this.Height.indexOf("px") == -1 && this.Height.indexOf("%") == -1) {
        this.Height = '100%'
    }
    this.Prestrain = MacPlayerConfig.prestrain;
    this.Buffer = MacPlayerConfig.buffer;
    this.Second = MacPlayerConfig.second;
    this.Flag = player_data.flag;
    this.Trysee = player_data.trysee;
    this.Points = player_data.points;
    this.Link = decodeURIComponent(player_data.link);
    this.PlayFrom = player_data.from;
    this.PlayNote = player_data.note;
    this.PlayServer = player_data.server == 'no' ? '' : player_data.server;
    this.PlayUrl = player_data.url;
    this.PlayUrlNext = player_data.url_next;
    this.PlayLinkNext = player_data.link_next;
    this.PlayLinkPre = player_data.link_pre;
    this.Id = player_data.id;
    this.Sid = player_data.sid;
    this.Nid = player_data.nid;
    if (MacPlayerConfig.server_list[this.PlayServer] != undefined) {
        this.PlayServer = MacPlayerConfig.server_list[this.PlayServer].des
    }
    if (MacPlayerConfig.player_list[this.PlayFrom] != undefined) {
        if (MacPlayerConfig.player_list[this.PlayFrom].ps == "1") {
            this.Parse = MacPlayerConfig.player_list[this.PlayFrom].parse == '' ? MacPlayerConfig.parse : MacPlayerConfig.player_list[this.PlayFrom].parse;
            this.PlayFrom = 'parse'
        }
    }
    this.Path = maccms.path + '/static/player/';
    if (this.Flag == "down") {
        MacPlayer.Down()
    } else {
        MacPlayer.Play()
    }
}
};

MacPlayer.Init();
