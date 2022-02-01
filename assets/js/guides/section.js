/**
 * 
 * Object that allows you to add new sections and handle events related to the sections.
 *
 * 
 */
/*jslint browser: true*/
/*global $, jQuery, alert*/
function section() {
	"use strict";

	var mySection = {
		settings : {
			sectionDataPath : "helpers/section_data.php?",
			sectionServicePath : "helpers/save_section.php?",
		},
		strings : {},
		bindUiActions : function() {
			mySection.clickTabOnSwitch();
			mySection.clickDeleteSection();
			mySection.chooseSectionForLayouts();
		},
		init: function () {

		    $( document ).ready(function() {
				mySection.bindUiActions();

				$('#layout_options_container').hide();
				$('#select_section_message').show();
		    });
		},
		autoSaveGuide: function() {
			var save = saveSetup();
			save.saveGuide();
			$("#response").hide();
			$('#save_guide').fadeOut();
		},
		viewSectionControls : function() {
			$(".sptab").each(function () {
				$(this).children().find('.sp_section_controls').show();
				$(this).children().find('.section_remove').hide();
				$(this).children().first().find('.sp_section_controls').trigger('click');
				$(this).children().first().find('.sp_section_controls').addClass('sp_section_selected');
				$(this).children().first().find('.sp_section_controls').parent('div').addClass('section_selected_area');
				if ($(this).children().size() > 1) {
					// do nothing
				} else {
					$(this).children().find('.sp_section_controls').trigger('click');
				}
			});
		},
		highlightFirstSectionControls : function () {
			var current_tab_index = $("#tabs").tabs('option', 'active');
		},
		makeAddSection : function(lstrSelector) {
			///////////////
			//function to add section to current tab
			//////////////
			$(lstrSelector).on('click', function() {
				//$(tabs).tabs();
				console.log('lstrSelector: ' + lstrSelector);
				var selectedTab = $('#tabs').tabs('option', 'active');

				var tab_id = $("[aria-controls='tabs-"+ selectedTab +"']").attr("id");
				var section_index_value = $('#tabs-' + selectedTab + ' .sp_section').last();
				var section_index = section_index_value.prevObject.last().index() + 1;
				var layout = "4-4-4";

				var newSection = mySection.addNewSection(section_index, layout, tab_id);
				newSection.then(function(data) {
					var selectedTab = $('#tabs').tabs('option', 'active');
					// add section block html with new section id
					var section_id = data.last_insert;
					var html = mySection.addNewSectionHtml(section_id);
					$('div#tabs-' + selectedTab).append(html);
					$(document).scrollTop($('body').height());
					// Make sure that the new section can accept drops
					var drop = drag();
					drop.makeDropable(".dropspotty");

					var selectedTab = $('#tabs').tabs('option', 'active');
					$('div#tabs-' + selectedTab)
					var newSectionBlock = $('#tabs-' + selectedTab + ' .sp_section_controls').last();
					newSectionBlock.trigger('click');
					newSectionBlock.addClass('sp_section_selected');
					newSectionBlock.parent('div').addClass('section_selected_area');
				});
				newSection.done(function (data) {
					window.scrollTo(0, $(".section_selected_area").offset().top);
				})
			});
		},

		addNewSection: function(section_index, layout, tab_id) {
			return $.ajax({
				url : mySection.settings.sectionServicePath,
				type : "GET",
				data : {
					section_index: section_index,
					layout: "4-4-4",
					tab_id: tab_id
				},
				dataType: "json"
			}).done(function() {
				mySection.getTabIds();
				mySection.getSectionIds();
			});
		},
		addNewSectionHtml: function(section_id) {

			var html = '<div id="section_' + section_id + '" class="sp_section pure-g" data-layout="4-4-4">\n' +
				'    <div class="sp_section_controls">\n' +
				'        <i class="fa fa-arrows section_sort" title="Move Section"></i>\n' +
				'        <i class="fa fa-trash-o section_remove" title="Delete Section" style="display: none;"></i>\n' +
				'\n' +
				'    </div>\n' +
				'    <div id="container-0" class="pure-u-1-3">\n' +
				'        <div class="container-area">\n' +
				'            <div class="dropspotty unsortable drop_area ui-droppable" id="dropspot-left-1">\n' +
				'                <span class="dropspot-text"> <i class="fa fa-dot-circle-o fa-lg"></i> Drop Here</span>\n' +
				'            </div>\n' +
				'\n' +
				'            <div class="portal-column sort-column portal-column-0 ui-sortable">\n' +
				'                <div></div>\n' +
				'            </div>\n' +
				'        </div>\n' +
				'    </div>\n' +
				'    <div id="container-1" class="pure-u-1-3">\n' +
				'        <div class="container-area">\n' +
				'            <div class="dropspotty unsortable drop_area ui-droppable" id="dropspot-center-1">\n' +
				'                <span class="dropspot-text"> <i class="fa fa-dot-circle-o fa-lg"></i> Drop Here</span>\n' +
				'            </div>\n' +
				'\n' +
				'            <div class="portal-column sort-column portal-column-1 ui-sortable">\n' +
				'                <div></div>\n' +
				'            </div>\n' +
				'        </div>\n' +
				'    </div>\n' +
				'    <div id="container-2" class="pure-u-1-3">\n' +
				'        <div class="container-area">\n' +
				'            <div class="dropspotty unsortable drop_area ui-droppable" id="dropspot-sidebar-1">\n' +
				'                <span class="dropspot-text"> <i class="fa fa-dot-circle-o fa-lg"></i> Drop Here</span>\n' +
				'            </div>\n' +
				'\n' +
				'            <div class="portal-column sort-column portal-column-2 ui-sortable">\n' +
				'                <div></div>\n' +
				'            </div>\n' +
				'        </div>\n' +
				'    </div>\n' +
				'    <div id="clearblock" style="clear:both;"></div>\n' +
				'    <!-- this just seems to allow the space to grow to fit dropbox areas -->\n' +
				'</div>';

			return html;
		},

		chooseSectionForLayouts : function () {
			/**
			 * If you click on the section controls, the section controls that you clicked on will be 
			 * hightlighted and the layouts control will effect that section. 
			 */
			$('body').on('click','.sp_section_controls', function() {

				var l = layout();

				$("#select_section_message").hide();
				$('#layout_options_container').show();

				// Removes existing highlights and controls
				$('.sp_section_controls').removeClass('sp_section_selected');
				$('.sp_section').removeClass('section_selected_area');
				$('.section_remove').hide();

				$('#layout_options_content').data('selected-section', '');

				// This adds the classes for highlighting
				$(this).toggleClass('sp_section_selected');
				$(this).parent().toggleClass('section_selected_area');

				// get section count of active tab
				var sectionCount = mySection.getSectionCount();
				if(sectionCount > 1) {
					$(this).children('.section_remove').show();
				} else {
					$(this).children('.section_remove').hide();
				}

				var selectedSectionId = $(this).parent().attr('id').split('_')[1];
				//console.log('selectedSectionId: ' + selectedSectionId);
				$('#layout_options_content').data('selected-section', selectedSectionId);

				l.activateLayoutButtons();
				// Highlight the layout that is associated with the section.
				l.highlightLayout($(this).parent())
			});
		},
		clickTabOnSwitch : function () {
			$('.ui-tabs-nav > li.child-tab').on('click', function() {
				$("#select_section_message").css('display', 'block');
				$('#layout_options_container').css('display', 'none');
				var tabIndex = $(this).attr('aria-controls').split('-')[1];
			});
		},
		setupHighlights : function() {
			// Hide the first section control in each of the tabs:
			$('.sp_section:first-child .sp_section_controls').hide();
		},
		clickDeleteSection: function() {
			$('body').on('click', '.section_remove', function() {
				var section_id = $(this).parent('.sp_section_selected').parent('.section_selected_area').attr('id').split('_')[1];

				// check for clone parent pluslets, if they exist this section cannot be deleted.
				var hasParentClones = mySection.hasParentClones(section_id);
				hasParentClones.then(function(data) {
					console.log(data.clone_parents_by_section);
					if(data.clone_parents_by_section.length > 0) {
						// this section has parent pluslets - do not delete
						mySection.deleteSectionRejectionDialog();
					} else {
						mySection.deleteSectionDialog(section_id);
					}
				});
			});
		},
		deleteSectionDialog: function(section_id) {

			$('<div id="dialog" class=\'delete_confirm\' title=\'Are you sure?\'>All content in this section will be deleted.</div>').dialog({
				autoOpen: false,
				modal: true,
				width: 'auto',
				height: 'auto',
				resizable: false,
				buttons: {
					Yes: function () {
						$("#section_" + section_id).remove();
						// save the guide
						mySection.autoSaveGuide();

						// scroll to first section
						window.scrollTo(0, $(".sp_section_controls").first().offset().top);

						// hightlight section controls
						var activeTab = $('#tabs').tabs('option', 'active');
						var currentTab = "#tabs-" + activeTab;
						$(currentTab).find('.sp_section_controls').first().trigger('click');
						$(currentTab).find('.sp_section_controls').first().addClass('sp_section_selected');
						$(currentTab).find('.sp_section_controls').first().parent('div').addClass('section_selected_area');

						$(this).dialog('close');
						return false;
					},
					Cancel: function () {
						$(this).dialog('close');
					}
				},
				close: function(event, ui) {
					$('.delete_confirm').remove();
				}
			});

			$('.delete_confirm').dialog('open');
			return false;
		},
		deleteSectionRejectionDialog: function() {

			$('<div id="dialog" class=\'delete_reject\' title=\'Are you sure?\'>This section contains boxes that have been linked, therefore it cannot be deleted.</div>').dialog({
				autoOpen: false,
				modal: true,
				width: 'auto',
				height: 'auto',
				resizable: false,
				buttons: {
					Cancel: function () {
						$(this).dialog('close');
					}
				},
				close: function(event, ui) {
					$('.delete_reject').remove();
				}
			});

			$('.delete_reject').dialog('open');
			return false;
		},
		getTabIds: function() {
			var nodes = $('.child-tab');
			var ids = [];
			$.each(nodes, function(data) {
				//console.log('tab ids: ' + this.id );
			});
		},
		getSectionIds: function() {
			var nodes = $('.sp_section');
			var ids = [];
			$.each(nodes, function(data) {
				//console.log('section ids: ' + this.id.split('_')[1] );
			});
		},
		fetchPlusletsBySectionId: function(section_id) {
			return $.ajax({
				url: "helpers/fetch_pluslets_by_section_id.php",
				type: "GET",
				data: 'section_id=' + section_id,
				dataType: "json"
			});
		},
		hasParentClones: function (section_id) {
			return $.ajax({
				url: "helpers/fetch_clone_parent_pluslets_by_section_id.php",
				type: "GET",
				data: 'section_id=' + section_id,
				dataType: "json"
			});
		},
		getSectionCount: function () {
			$('#tabs').tabs();
			var activeTab = $('#tabs').tabs('option', 'active');
			var sectionCount = $('#tabs-' + activeTab).children('.sp_section').size();
			return sectionCount;
		}
	};
	return mySection;
}
