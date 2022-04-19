tinymce.PluginManager.add('kityformula-editor', function(editor, url) {

    var baseURL = tinymce.baseURL+'/plugins/kityformula-editor/kityFormula.html';

    editor.on('dblclick',function(){
        var sel=editor.selection.getContent();
        var path=/\<img(.*?)src="data:image\/png;base64,[A-Za-z0-9+/=]*"(.*?)data-latex="(.*?)" \/>/g;
        var path2=/data-latex="(.*?)"/g;

        if(sel.search(path)==0){
            sel.replace(path2,function($0,$1){
                var param=encodeURIComponent($1);
                openDialog(param);
                return $0;
            });
        };
    });

    var openDialog = function(param) {
        return editor.windowManager.openUrl({
            title: '插入公式',
            size: 'large',
            width: 785,
            height: 475,
            url:param?baseURL+"?c="+param:baseURL,
            buttons: [
                {
                    type: 'cancel',
                    text: 'Close'
                },
                {
                    type: 'custom',
                    text: 'Save',
                    name: 'save',
                    primary: true
                },
            ],
            onAction: function (api, details) {
                switch (details.name) {
                    case 'save':
                        api.sendMessage("save");
                        break;
                    default:
                        break;
                };
            }
        });
    };

    
    editor.ui.registry.addButton('kityformula-editor', {
        text: '公式',
        tooltip: '插入公式',
        onAction: function() {
            openDialog();
        }
    });
    editor.ui.registry.addMenuItem('kityformula-editor', {
        text: '公式',
        onAction: function() {
            openDialog();
        }
    });
    return {
        getMetadata: function() {
            return  {
                name: "公式",
                url: "http://hgcserver.gitee.io",
            };
        }
    };
});