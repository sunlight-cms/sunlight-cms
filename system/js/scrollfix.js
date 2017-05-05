"use_strict";var Shira;!function(t,i){!function(t){t.Watcher=function(e,s){this.element=e,this.options=i.extend({},t.Watcher.defaults,s),i(e).data("shira.scrollfix",this)},t.Watcher.defaults={fixClass:"scroll-fix",fixTop:0,fixOffset:0,unfixOffset:0,onUpdateFixed:null,syncSize:!0,syncPosition:!0,style:!0},t.Watcher.prototype={element:null,substitute:null,options:null,fixed:!1,attached:!1,getElementX:function(t){var i=0;do i+=t.offsetLeft;while(t=t.offsetParent);return i},getElementY:function(t){var i=0;do i+=t.offsetTop;while(t=t.offsetParent);return i},fix:function(){this.fixed||(this.substitute=i(this.element.cloneNode(!1)).css("visibility","hidden").height(i(this.element).height()).insertAfter(this.element)[0],this.options.style&&i(this.element).css("position","fixed").css("top",this.options.fixTop+"px"),i(this.element).addClass(this.options.fixClass),this.fixed=!0,this.dispatchEvent("fixed"))},updateFixed:function(){if(this.options.syncSize&&i(this.element).width(i(this.substitute).width()),this.options.syncPosition){var t=i(window).scrollLeft(),e=this.getElementX(this.substitute);i(this.element).css("left",e-t+"px")}null!==this.options.onUpdateFixed&&this.options.onUpdateFixed(this),this.dispatchEvent("update")},unfix:function(){if(this.fixed){i(this.substitute).remove(),this.substitute=null;var t={};this.options.syncPosition&&(t.left=""),this.options.syncSize&&(t.width=""),this.options.style&&(t.position="",t.top=""),i(this.element).css(t).removeClass(this.options.fixClass),this.fixed=!1,this.dispatchEvent("unfixed")}},attach:function(){if(!this.attached){var t=this;this.updateEventHandler=function(){t.pulse()},i(window).scroll(this.updateEventHandler).resize(this.updateEventHandler),this.attached=!0,this.pulse()}},detach:function(){this.attached&&(this.unfix(),i(window).unbind("scroll",this.updateEventHandler).unbind("resize",this.updateEventHandler),this.attached=!1)},pulse:function(){var t=i(window).scrollTop();this.fixed?t<=this.getElementY(this.substitute)+this.options.unfixOffset&&!this.dispatchEvent("unfix").isDefaultPrevented()?this.unfix():this.updateFixed():t>=this.getElementY(this.element)+this.options.fixOffset&&!this.dispatchEvent("fix").isDefaultPrevented()&&(this.fix(),this.updateFixed())},dispatchEvent:function(t){var e=new i.Event(t+".shira.scrollfix",{watcher:this});return i(this.element).trigger(e),e}},i.fn.scrollFix=function(i){return this.length>0&&new t.Watcher(this[0],i).attach(),this}}(t.ScrollFix||(t.ScrollFix={}))}(Shira||(Shira={}),jQuery);
