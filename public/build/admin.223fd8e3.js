(self.webpackChunk=self.webpackChunk||[]).push([[328],{201:(t,e,a)=>{"use strict";a(9826),a(1539),a(3943);var i=a(7228),n=a.n(i),s=(a(2665),a(9755));s((function(){var t=s('input[data-toggle="tagsinput"]');if(t.length){var e=new(n())({local:t.data("tags"),queryTokenizer:n().tokenizers.whitespace,datumTokenizer:n().tokenizers.whitespace});e.initialize(),t.tagsinput({trimValue:!0,focusClass:"focus",typeaheadjs:{name:"tags",source:e.ttAdapter()}})}})),s(document).on("submit","form[data-confirmation]",(function(t){var e=s(this),a=s("#confirmationModal");"yes"!==a.data("result")&&(t.preventDefault(),a.off("click","#btnYes").on("click","#btnYes",(function(){a.data("result","yes"),e.find('input[type="submit"]').attr("disabled","disabled"),e.trigger("submit")})).modal("show"))}))},4327:()=>{}},t=>{t.O(0,[41,384],(()=>{return e=201,t(t.s=e);var e}));t.O()}]);