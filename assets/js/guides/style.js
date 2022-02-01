/**
 * Style changes that are triggered with js. 
 * 
 * @constructor style
 * 
 * 
 */

function style() {
	"use strict";

    var myStyle = {
        settings: {
            globalHeader: $("#header, #subnavcontainer")       
        },
        strings: {
        },
        bindUiActions: function () {
        	myStyle.hideControlHeader();
        },
        init: function () {
            /** Since we are in the guide creation interface we'll need to hide the bar at the top */
            myStyle.settings.globalHeader.hide();
            myStyle.fixFlashFOUC();
            myStyle.bindUiActions();
        },
        fixFlashFOUC: function () {
            $(".guidewrapper").css("display", "inline-block");
            $("#main-options").css("display", "block");
        }, 
        hideControlHeader : function () {
        	
        	 $('#hide_header').click(function(event) {
        	   $("#header, #subnavcontainer").toggle('fast');
               $("#header").toggleClass("toggle_header_fix");
               $("#guide-parent-wrap").toggleClass("toggle_header_margin");
               $("#response").toggleClass("toggle_response_margin");
        	  });
        }
   
       
        

    };
    return myStyle;
}