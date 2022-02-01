<style>
	.panel-list-item {
		cursor: move;
	}
	.flyout-tabs {
		max-height: 130px;
	}
	#drag_drop_tabs_lists {
		display: block;
		margin-top:50px;
		margin-bottom:20px;
	}
	#categories-chosen {
		min-height:80px;
		border:1px solid #f8f8f8;
		margin-bottom:15px;
	}


</style>
<div id="tabs_options" class="second-level-content"  style="display: none;">

	<h3><?php print _("Reorder Tabs"); ?></h3>
	<ul class="flyout-tabs panel-list" id="flayout-tab-list"></ul>
	<button id="save_tab_order_btn" class="pure-button pure-button-primary" type="submit"><?php echo _('Save Tab Order'); ?></button>


	<div id="drag_drop_tabs_lists">

		<h3><?php echo _('Drag & drop tabs to create new guide.'); ?></h3>
		<div class='source-news'>
            <p><?php echo _('Some content types cannot be copied: '); ?><?php echo _('All Items by Source'); ?>, <?php echo _('Key to Icons'); ?>,  <?php echo _('FAQs'); ?>, <?php echo _('Books'); ?>.</p>
			<ul id="categories-source" class='news-list categories-sortable flyout-tabs new-guide-tabs panel-list'>

			</ul>
		</div>

		<div id="new_guide_tabs_container">
			<h4><?php echo _('New Guide (drop tabs below)'); ?></h4>
			<div class='interested-in '>
				<ul id="categories-chosen" class='news-list interested categories-sortable new-guide-tabs panel-list'>

				</ul>
			</div>
			<button class="button pure-button pure-button-primary create-guide"> <?php echo _('Create New Guide'); ?></button>
		</div>

	</div>


</div>