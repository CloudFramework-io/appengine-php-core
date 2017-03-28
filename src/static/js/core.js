// cloudframework.io js Core class
// It requires: jquery (min 2.1.1) https://code.jquery.com/jquery-git.min.js
// It requires https://cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js

var core = new function () {
    this.version = '1.0';
    this.app = 'cloudframework';
    this.debug = false;
    this.request = new function() {
        this.token =''; // X-DS-TOKEN sent in all calls
        this.key =''; // X-WEB-KEY sent in all calls
        this.headers = {};
        this.get = function(endpoint, callback, errorcallback, typeReturnedExpected) {

            // Allow to pass callbacks calling class methods like myApp.assignHash
            if(typeof callback != 'undefined'  && null != callback) {
                var namespaces = callback.split(".");
                callback = window[namespaces[0]];
                for(var i = 1,tr=namespaces.length; i < tr; i++) {
                    callback = callback[namespaces[i]];
                }
            }
            if(typeof errorcallback != 'undefined'  && null != errorcallback) {
                var namespaces = errorcallback.split(".");
                errorcallback = window[namespaces[0]];
                for(var i = 1,tr=namespaces.length; i < tr; i++) {
                    errorcallback = errorcallback[namespaces[i]];
                }
            }

            var contentType = 'json';
            if(typeof typeReturnedExpected != 'undefined' && typeReturnedExpected != 'data') contentType=typeReturnedExpected;

            // this.token is passed in X-DS-TOKEN header
            if(typeof core.request.token != 'undefined' &&  core.request.token!='')
                core.request.headers['X-DS-TOKEN'] = core.request.token;
            if(typeof core.request.key != 'undefined' &&  core.request.key!='')
                core.request.headers['X-WEB-KEY'] = core.request.key;

            // Doing the call
            $.ajax({
                type: "GET",
                url: endpoint,
                headers:  core.request.headers,
                dataType: contentType,
                async: true,
                success: function(response){
                    if(contentType=='json' && (true != response.success || typeof response.data == undefined)) {
                        console.log('core.request.get(), Missing response.success==true or missing response.data Calling: '+endpoint);
                        console.log(response);
                    } else {
                        if(typeof callback != 'undefined'  && null != callback) {
                            if(typeof typeReturnedExpected != 'undefined' && typeReturnedExpected=='data') {
                                callback(response.data);
                            } else {
                                callback(response);
                            }
                        }
                    }

                },
                error: function(e){
                    console.log('core.request.get_json_data(), error calling '+ endpoint);
                    if(typeof errorcallback != 'undefined' && null != errorcallback) {
                        errorcallback(e);
                    }
                }
            });
        };

        this.get_json_data = function(endpoint, callback, errorcallback) {
            core.request.get(endpoint, callback, errorcallback, 'data');
        }

    };
    this.template = new function() {
        this.templates = [];

        this.renderFromTemplate = function (tpl,data,mode,id) {
            var ret = '';
            if(typeof core.template.templates[tpl] == 'undefined' ) {
                core.template.templates[tpl] = '..loading';
                $('#' + id).html('..loading');
                endpoint = '/'+core.app+'/templates/'+tpl+'.htm';
                $.ajax({
                    type: "GET",
                    url: endpoint,
                    success: function(response){
                        core.template.templates[tpl] = response;
                        Mustache.parse(core.template.templates[tpl]);
                        core.template.render(response, data,mode,id);

                    },
                    error: function(){
                        core.template.templates[tpl]='error loading template';
                        alert('template does not exist: '+ endpoint);
                    }
                });
                return null;
            } else if(core.template.templates[tpl] == '..loading') {
                if('..waiting'== $('#' + id).html()) {
                    console.log('second call');
                    return;
                }
                $('#' + id).html('..waiting');
                window.setTimeout(core.template.renderFromTemplate,100,tpl,data,mode,id);
                return null;
            } else {
                core.template.render(core.template.templates[tpl], data,mode,id);
                return null;
            }
        };

        this.renderFromScript = function (idScript,data,mode,id) {
            var html = $('#'+idScript).html();
            if('undefined' == typeof html) {
                console.log('Error trying to read as HTML template id: '+idScript);

            } else {
                core.template.render(html, data,mode,id);
            }

        };

        this.render = function (html,data,mode,id) {
            if(mode=='html') {
                var htmlRendered = Mustache.render(html, data);
                if(core.debug) {
                    console.log(htmlRendered);
                }
                $('#' + id).html(htmlRendered);
                pageSetUp();
            }
        };
    };
    this.cache = new function () {
        this.isAvailable = true;
        if (typeof(Storage) == "undefined") {
            console.log('Cache is not supported in this browser');
            this.available = false;
        };

        this.set = function(key,value) {
            if(core.cache.isAvailable) {
                localStorage.setItem(key, value);
            }
        };
        this.get = function(key,value) {
            if(core.cache.isAvailable) {
                localStorage.getItem(key);
            }
        };
        this.delete = function(key) {
            if(core.cache.isAvailable) {
                localStorage.removeItem(key);
            }
        };
    };
    this.cookies = new function() {
        this.path = { path: '/' };
        this.remove = function(varname) {
            if(typeof varname != 'undefined') {
                $.removeCookie(varname,core.cookies.path);
                if(core.debug) console.log('removed cookie '+varname);
            }
        };
        this.set = function(varname,data) {
            $.cookie(varname,data, core.cookies.path);
            if(core.debug) console.log('set cookie '+varname);
        };
        this.get = function(varname) {
            return $.cookie(varname);
        };
    };
    this.params = function(pos) {
        var path = window.location.pathname.split( '/' );
        return path[pos];
    };
    this.formParams = function(name) {
        var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
        if (results==null) {
            results = new RegExp('[\?&](' + name + ')[&#$]*').exec(window.location.href);
            if (results==null) return null;
            else return true;
        } else {
            return results[1] || true;
        }
    };
};