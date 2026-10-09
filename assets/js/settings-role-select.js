/* globals wptRoleSelect */
( function () {
	'use strict';

	function attachTagRemoveHandlers( tagsContainer, idsContainer, select ) {
		tagsContainer
			.querySelectorAll( '.wpt-role-tag-remove' )
			.forEach( function ( btn ) {
				if ( btn.dataset.handlerAttached ) {
					return;
				}

				btn.dataset.handlerAttached = 'true';

				btn.addEventListener( 'click', function () {
					const slug = btn.dataset.roleSlug;

					const hidden = idsContainer.querySelector(
						'input[value="' + slug + '"]'
					);

					if ( hidden ) {
						hidden.remove();
					}

					btn.closest( '.wpt-role-tag' ).remove();

					const option = select.querySelector(
						'option[value="' + slug + '"]'
					);

					if ( option ) {
						option.hidden = false;
						option.disabled = false;
					}
				} );
			} );
	}

	function addRoleTag(
		slug,
		label,
		tagsContainer,
		idsContainer,
		settingKey,
		select
	) {
		const tag = document.createElement( 'span' );
		tag.className = 'wpt-role-tag';

		const labelEl = document.createElement( 'span' );
		labelEl.className = 'wpt-role-tag-label';
		labelEl.textContent = label;

		const removeBtn = document.createElement( 'button' );
		removeBtn.type = 'button';
		removeBtn.className = 'wpt-role-tag-remove';
		removeBtn.dataset.roleSlug = slug;
		removeBtn.setAttribute( 'aria-label', wptRoleSelect.removeLabel );
		removeBtn.textContent = '×';
		tag.appendChild( labelEl );
		tag.appendChild( removeBtn );
		tagsContainer.appendChild( tag );

		const hidden = document.createElement( 'input' );
		hidden.type = 'hidden';
		hidden.name = 'wpt_settings[' + settingKey + '][]';
		hidden.value = slug;
		idsContainer.appendChild( hidden );
		attachTagRemoveHandlers( tagsContainer, idsContainer, select );
	}

	function initRoleSelect( wrapper ) {
		const settingKey = wrapper.dataset.settingKey;
		const select = wrapper.querySelector( '.wpt-role-select-input' );
		const tagsContainer = wrapper.querySelector( '.wpt-role-tags' );
		const idsContainer = wrapper.querySelector( '.wpt-role-ids' );

		attachTagRemoveHandlers( tagsContainer, idsContainer, select );

		select.addEventListener( 'change', function () {
			const slug = select.value;

			if ( ! slug ) {
				return;
			}

			const option = select.selectedOptions[ 0 ];

			addRoleTag(
				slug,
				option.textContent,
				tagsContainer,
				idsContainer,
				settingKey,
				select
			);

			option.hidden = true;
			option.disabled = true;
			select.value = '';
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		document
			.querySelectorAll( '.wpt-role-select' )
			.forEach( function ( wrapper ) {
				initRoleSelect( wrapper );
			} );
	} );
} )();
