jQuery(function ($) {
    function isButtonModeEnabled() {
        return typeof aavData !== 'undefined' && aavData.displayMode === 'button';
    }

    function getButtonPresentation(attributeName, value) {
        if (typeof aavData === 'undefined' || !aavData.buttonPresentations) {
            return null;
        }

        const normalizedAttributeName = String(attributeName || '').replace(/^attribute_/, '');
        const candidates = [
            attributeName,
            'attribute_' + normalizedAttributeName,
            normalizedAttributeName
        ];
        const valueCandidates = [
            value,
            decodeURIComponent(String(value || '')),
            String(value || '').replace(/^\/+|\/+$/g, '')
        ];

        for (let i = 0; i < candidates.length; i += 1) {
            const key = candidates[i];
            if (!key || !aavData.buttonPresentations[key]) {
                continue;
            }

            for (let j = 0; j < valueCandidates.length; j += 1) {
                const valueKey = valueCandidates[j];
                if (Object.prototype.hasOwnProperty.call(aavData.buttonPresentations[key], valueKey)) {
                    return aavData.buttonPresentations[key][valueKey];
                }
            }
        }

        return null;
    }

    function getFlatButtonPresentation(value, text) {
        if (typeof aavData === 'undefined' || !aavData.buttonPresentationsFlat) {
            return null;
        }

        const candidates = [
            value,
            decodeURIComponent(String(value || '')),
            String(value || '').replace(/^\/+|\/+$/g, ''),
            text,
            String(text || '').trim(),
            String(text || '').trim().toLowerCase(),
            String(text || '').trim().toLowerCase().replace(/\s+/g, '-')
        ];

        for (let i = 0; i < candidates.length; i += 1) {
            const key = candidates[i];
            if (key && Object.prototype.hasOwnProperty.call(aavData.buttonPresentationsFlat, key)) {
                return aavData.buttonPresentationsFlat[key];
            }
        }

        return null;
    }

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function getButtonLabelInlineStyle(hasMedia) {
        if (typeof aavData === 'undefined') {
            return ' style="text-transform:none !important;"';
        }

        const mediaTransform = aavData.buttonMediaLabelTextTransform;
        const resolvedMediaTransform = mediaTransform && mediaTransform !== 'none' ? mediaTransform : (aavData.buttonTextTransform || 'none');
        const transform = hasMedia ? resolvedMediaTransform : (aavData.buttonTextTransform || '');
        return ' style="text-transform:' + escapeHtml(transform || 'none') + ' !important;"';
    }

    function applyInlineTextTransforms($scope) {
        if (!$scope || !$scope.length) {
            return;
        }

        const defaultTransform = typeof aavData !== 'undefined' ? (aavData.buttonTextTransform || 'none') : 'none';
        const mediaTransform = typeof aavData !== 'undefined'
            ? ((aavData.buttonMediaLabelTextTransform && aavData.buttonMediaLabelTextTransform !== 'none')
                ? aavData.buttonMediaLabelTextTransform
                : (aavData.buttonTextTransform || 'none'))
            : 'none';

        $scope.find('.aav-attribute-button').each(function () {
            const $button = $(this);
            const transform = $button.hasClass('has-media') ? mediaTransform : defaultTransform;
            $button.find('.aav-attribute-button-label').attr('style', 'text-transform:' + (transform || 'none') + ' !important;');
        });
    }

    function setBlock(selector, title, content) {
        const $box = $(selector);
        if (!$box.length) return;

        if (content && String(content).trim() !== '') {
            $box.html('<h3>' + escapeHtml(title) + '</h3><div>' + content + '</div>').show();
        } else {
            $box.html('').hide();
        }
    }

    function setBadge(content) {
        const $box = $('#aav-badge');
        if (!$box.length) return;

        if (content && String(content).trim() !== '') {
            $box.html('<span class="aav-badge-pill">' + escapeHtml(content) + '</span>').show();
        } else {
            $box.html('').hide();
        }
    }

    function setPdf(url) {
        const $box = $('#aav-pdf');
        if (!$box.length) return;

        if (url) {
            $box.html('<h3>PDF wariantu</h3><p><a href="' + encodeURI(url) + '" target="_blank" rel="noopener">Pobierz PDF</a></p>').show();
        } else {
            $box.html('').hide();
        }
    }

    function setVideo(url) {
        const $box = $('#aav-video');
        if (!$box.length) return;

        if (url) {
            $box.html('<h3>Wideo</h3><p><a href="' + encodeURI(url) + '" target="_blank" rel="noopener">Zobacz wideo</a></p>').show();
        } else {
            $box.html('').hide();
        }
    }

    function setGallery(images) {
        const $box = $('#aav-gallery');
        if (!$box.length) return;

        if (Array.isArray(images) && images.length) {
            const html = images.map(function (url) {
                return '<a href="' + encodeURI(url) + '" target="_blank" rel="noopener"><img src="' + encodeURI(url) + '" alt="" /></a>';
            }).join('');
            $box.html('<h3>Galeria wariantu</h3><div class="aav-gallery-grid">' + html + '</div>').show();
        } else {
            $box.html('').hide();
        }
    }

    function renderVariationInfo(variation) {
        setBadge(variation.aav_badge || '');
        setBlock('#aav-flavor-desc', 'Opis smaku', variation.aav_flavor_desc || '');
        setBlock('#aav-ingredients', 'Skład produktu', variation.aav_ingredients || '');
        setBlock('#aav-nutrition', 'Wartości odżywcze', variation.aav_nutrition || '');
        setPdf(variation.aav_pdf_url || '');
        setVideo(variation.aav_video_url || '');
        setGallery(variation.aav_gallery || []);

        const $tab = $('#aav-tab-variation-content');
        if ($tab.length) {
            let html = '';
            if (variation.aav_badge) html += '<p><strong>Etykieta:</strong> ' + escapeHtml(variation.aav_badge) + '</p>';
            if (variation.aav_flavor_desc) html += '<div><h3>Opis smaku</h3>' + variation.aav_flavor_desc + '</div>';
            if (variation.aav_ingredients) html += '<div><h3>Skład produktu</h3>' + variation.aav_ingredients + '</div>';
            if (variation.aav_nutrition) html += '<div><h3>Wartości odżywcze</h3>' + variation.aav_nutrition + '</div>';
            if (variation.aav_pdf_url) html += '<p><a href="' + encodeURI(variation.aav_pdf_url) + '" target="_blank" rel="noopener">Pobierz PDF</a></p>';
            if (variation.aav_video_url) html += '<p><a href="' + encodeURI(variation.aav_video_url) + '" target="_blank" rel="noopener">Zobacz wideo</a></p>';
            if (!html) html = '<p>Brak dodatkowych danych dla tego wariantu.</p>';
            $tab.html(html);
        }
    }

    function clearVariationInfo() {
        setBadge('');
        setBlock('#aav-flavor-desc', 'Opis smaku', '');
        setBlock('#aav-ingredients', 'Skład produktu', '');
        setBlock('#aav-nutrition', 'Wartości odżywcze', '');
        setPdf('');
        setVideo('');
        setGallery([]);
        $('#aav-tab-variation-content').html('<p>Wybierz wariant, aby zobaczyć dodatkowe informacje.</p>');
    }

    function cleanProductBaseUrl() {
        if (typeof aavData !== 'undefined' && aavData.productBaseUrl) {
            return aavData.productBaseUrl;
        }
        return window.location.origin + window.location.pathname;
    }

    function updateUrlFromVariation(variation) {
        if (variation && variation.aav_permalink) {
            window.history.replaceState({}, '', variation.aav_permalink);
        }
    }

    function resolveAcfFieldTarget(fieldName) {
        if (!fieldName) {
            return $();
        }

        const selectors = [
            '.acf-field[data-name="' + fieldName + '"]',
            '[data-name="' + fieldName + '"].acf-field',
            '.acf-field-' + fieldName,
            '[data-name="' + fieldName + '"]'
        ];

        for (let i = 0; i < selectors.length; i += 1) {
            const $target = $(selectors[i]).first();
            if ($target.length) {
                return $target;
            }
        }

        return $();
    }

    function maybeMoveVariationFormByAcf($form) {
        if (typeof aavData === 'undefined' || !aavData.variationFormLocation) {
            return;
        }

        const location = aavData.variationFormLocation;
        if (location.position !== 'acf_field' || !location.acfField) {
            return;
        }

        const $target = resolveAcfFieldTarget(location.acfField);
        if (!$target.length) {
            return;
        }

        if (location.acfPlacement === 'before') {
            $target.before($form);
        } else {
            $target.after($form);
        }
    }

    function currentUrlHasVariationPath() {
        const baseUrl = cleanProductBaseUrl().replace(/\/$/, '');
        const current = (window.location.origin + window.location.pathname).replace(/\/$/, '');
        if (current === baseUrl) return false;
        return current.indexOf(baseUrl + '/') === 0;
    }

    function getRequestedAttributesFromPath($form) {
        const baseUrl = cleanProductBaseUrl();
        const current = window.location.origin + window.location.pathname;
        const suffix = current.replace(baseUrl.replace(/\/$/, ''), '').replace(/^\/+/, '').replace(/\/+$/, '');
        if (!suffix) return {};

        const parts = suffix.split('/').filter(Boolean);
        if (!parts.length) return {};

        const requested = {};
        const $selects = $form.find('select[name^="attribute_"]');
        if (!$selects.length) return requested;

        if (parts.length % 2 === 0) {
            for (let i = 0; i < parts.length; i += 2) {
                requested[parts[i]] = parts[i + 1];
            }
            return requested;
        }

        $selects.each(function (index) {
            const name = (($(this).attr('name') || '').replace(/^attribute_/, ''));
            if (parts[index]) {
                requested[name] = parts[index];
            }
        });

        return requested;
    }

    function matchesRequestedAttributes(variation, requested) {
        if (!variation || !variation.attributes) return false;

        for (const key in requested) {
            const attrKey = 'attribute_' + key;
            if (!Object.prototype.hasOwnProperty.call(variation.attributes, attrKey)) {
                return false;
            }

            const actual = variation.attributes[attrKey];
            if (actual && actual !== requested[key]) {
                return false;
            }
        }

        return true;
    }

    function findMatchingVariation($form, requested) {
        if (!requested || !Object.keys(requested).length) return null;

        const variations = $form.data('product_variations');
        if (!Array.isArray(variations)) return null;

        for (let i = 0; i < variations.length; i += 1) {
            if (matchesRequestedAttributes(variations[i], requested)) {
                return variations[i];
            }
        }

        return null;
    }

    function findVariationById($form, variationId) {
        if (!variationId) return null;

        const variations = $form.data('product_variations');
        if (!Array.isArray(variations)) return null;

        for (let i = 0; i < variations.length; i += 1) {
            if (parseInt(variations[i].variation_id, 10) === parseInt(variationId, 10)) {
                return variations[i];
            }
        }

        return null;
    }

    function findVariationPresentation($form, attributeName, value) {
        const variations = $form.data('product_variations');
        if (!Array.isArray(variations)) {
            return null;
        }

        for (let i = 0; i < variations.length; i += 1) {
            const variation = variations[i];
            if (!variation || !variation.attributes || variation.attributes[attributeName] !== value) {
                continue;
            }

            return {
                imageUrl: variation.image && (variation.image.thumb_src || variation.image.src) ? (variation.image.thumb_src || variation.image.src) : '',
                hoverImageUrl: variation.image && variation.image.src ? variation.image.src : ''
            };
        }

        return null;
    }

    function applyVariationToForm($form, variation) {
        if (!variation) return false;

        Object.keys(variation.attributes || {}).forEach(function (attributeKey) {
            const value = variation.attributes[attributeKey];
            const $select = $form.find('select[name="' + attributeKey + '"]');

            if (!$select.length || !value) {
                return;
            }

            $select.val(value);
        });

        renderVariationInfo(variation);
        updateUrlFromVariation(variation);
        $form.find('input[name="variation_id"]').val(variation.variation_id).trigger('change');
        $form.trigger('found_variation', [variation]);

        return true;
    }

    function buildButtonMarkup($form, $select) {
        const attributeName = $select.attr('name') || '';
        const labelText = $select.closest('tr, .value, .variations').prev('label').text() || $select.closest('tr').find('label').first().text() || '';
        const icon = typeof aavData !== 'undefined' ? (aavData.buttonIcon || '') : '';
        let buttons = '';

        $select.find('option').each(function () {
            const $option = $(this);
            const value = $option.val();
            const text = $option.text();
            const presentation = getButtonPresentation(attributeName, value) || getFlatButtonPresentation(value, text) || {};
            const variationPresentation = findVariationPresentation($form, attributeName, value) || {};
            const imageUrl = presentation.imageUrl || variationPresentation.imageUrl || '';
            const hoverImageUrl = presentation.hoverImageUrl || variationPresentation.hoverImageUrl || '';
            const imageSize = parseInt(presentation.imageSize || 0, 10) || 0;
            const imageHtml = presentation && presentation.imageUrl ? '<span class="aav-attribute-button-image"><img src="' + escapeHtml(presentation.imageUrl) + '" alt="" /></span>' : '';
            const iconValue = presentation && presentation.icon ? presentation.icon : icon;
            const iconHtml = !imageHtml && iconValue ? '<span class="aav-attribute-button-icon">' + escapeHtml(iconValue) + '</span>' : '';
            const swatchHtml = !imageHtml && !iconHtml && presentation && presentation.color ? '<span class="aav-attribute-button-swatch" style="background:' + escapeHtml(presentation.color) + ';"></span>' : '';
            const styleVars = [];
            if (presentation && presentation.color) {
                styleVars.push('--aav-term-color:' + escapeHtml(presentation.color));
            }
            if (imageSize > 0) {
                styleVars.push('--aav-button-image-size:' + imageSize + 'px');
            }
            const styleAttr = styleVars.length ? ' style="' + styleVars.join(';') + ';"' : '';
            const hoverImageAttr = hoverImageUrl ? ' data-hover-image="' + escapeHtml(hoverImageUrl) + '"' : '';

            if (!value) {
                return;
            }

            const finalImageHtml = imageUrl ? '<span class="aav-attribute-button-image"><img src="' + escapeHtml(imageUrl) + '" alt="" /></span>' : '';
            const hasMediaClass = finalImageHtml ? ' has-media' : '';
            const labelStyle = getButtonLabelInlineStyle(!!finalImageHtml);
            const accessibleLabel = ' aria-label="' + escapeHtml(text) + '" title="' + escapeHtml(text) + '"';

            buttons += '<button type="button" class="aav-attribute-button' + hasMediaClass + '" data-attribute-name="' + escapeHtml(attributeName) + '" data-value="' + escapeHtml(value) + '"' + hoverImageAttr + styleAttr + accessibleLabel + '>' +
                finalImageHtml +
                (!finalImageHtml ? iconHtml : '') +
                (!finalImageHtml ? swatchHtml : '') +
                '<span class="aav-attribute-button-label"' + labelStyle + '>' + escapeHtml(text) + '</span>' +
                '</button>';
        });

        if (!buttons) {
            return '';
        }

        return '<div class="aav-attribute-buttons" data-attribute-name="' + escapeHtml(attributeName) + '">' +
            (labelText ? '<div class="aav-attribute-buttons-title">' + escapeHtml(labelText) + '</div>' : '') +
            '<div class="aav-attribute-buttons-grid">' + buttons + '</div>' +
            '</div>';
    }

    function syncButtonsWithSelect($form) {
        const isTouchLike = window.matchMedia && window.matchMedia('(hover: none)').matches;

        $form.find('select[name^="attribute_"]').each(function () {
            const $select = $(this);
            const attributeName = $select.attr('name');
            const selectedValue = $select.val();
            const $buttons = $form.find('.aav-attribute-button[data-attribute-name="' + attributeName + '"]');

            // Always clear state first so stale selections from initial markup or theme scripts do not persist.
            $buttons.removeClass('is-selected');

            $buttons.each(function () {
                const $button = $(this);
                const value = $button.data('value');
                const option = $select.find('option[value="' + value + '"]');
                const isDisabled = option.length ? option.prop('disabled') : false;

                if (selectedValue === value && selectedValue !== '') {
                    $button.addClass('is-selected');
                }
                $button.prop('disabled', !!isDisabled);
                $button.toggleClass('is-disabled', !!isDisabled);

                if (isTouchLike) {
                    const hoverImage = $button.data('hover-image');
                    if (selectedValue === value && selectedValue !== '' && hoverImage && !$button.find('.aav-attribute-button-hover-image').length) {
                        $button.append('<span class="aav-attribute-button-hover-image"><img src="' + escapeHtml(hoverImage) + '" alt="" /></span>');
                    }

                    if (selectedValue !== value || selectedValue === '') {
                        $button.find('.aav-attribute-button-hover-image').remove();
                    }
                }
            });
        });
    }

    function initButtonMode($form) {
        if (!isButtonModeEnabled()) {
            return;
        }

        if ($form.hasClass('aav-button-mode-ready')) {
            applyInlineTextTransforms($form);
            syncButtonsWithSelect($form);
            return;
        }

        $form.addClass('aav-button-mode aav-button-mode-ready');
        $form.attr('data-animation', typeof aavData !== 'undefined' ? (aavData.buttonAnimation || 'lift') : 'lift');

        $form.find('select[name^="attribute_"]').each(function () {
            const $select = $(this);
            const markup = buildButtonMarkup($form, $select);

            if (!markup) {
                return;
            }

            $select.addClass('aav-hidden-select');
            $select.after(markup);
        });

        $form.on('click', '.aav-attribute-button', function () {
            const $button = $(this);
            if ($button.prop('disabled')) {
                return;
            }

            const attributeName = $button.data('attribute-name');
            const value = $button.data('value');
            const $select = $form.find('select[name="' + attributeName + '"]');

            if (!$select.length) {
                return;
            }

            $select.val(value).trigger('change');
            syncButtonsWithSelect($form);
        });

        $form.on('mouseenter', '.aav-attribute-button', function () {
            const $button = $(this);
            const hoverImage = $button.data('hover-image');

            if (!hoverImage || $button.find('.aav-attribute-button-hover-image').length) {
                return;
            }

            $button.append('<span class="aav-attribute-button-hover-image"><img src="' + escapeHtml(hoverImage) + '" alt="" /></span>');
        });

        $form.on('mouseleave', '.aav-attribute-button', function () {
            $(this).find('.aav-attribute-button-hover-image').remove();
        });

        $form.on('focusin', '.aav-attribute-button', function () {
            const $button = $(this);
            const hoverImage = $button.data('hover-image');

            if (!hoverImage || $button.find('.aav-attribute-button-hover-image').length) {
                return;
            }

            $button.append('<span class="aav-attribute-button-hover-image"><img src="' + escapeHtml(hoverImage) + '" alt="" /></span>');
        });

        $form.on('focusout', '.aav-attribute-button', function () {
            $(this).find('.aav-attribute-button-hover-image').remove();
        });

        applyInlineTextTransforms($form);
        syncButtonsWithSelect($form);
    }

    function preloadVariationFromPath($form) {
        const requested = getRequestedAttributesFromPath($form);
        const currentVariationId = typeof aavData !== 'undefined' ? parseInt(aavData.currentVariationId || 0, 10) : 0;
        const variationById = findVariationById($form, currentVariationId);

        if (variationById) {
            setTimeout(function () {
                applyVariationToForm($form, variationById);
                $form.trigger('check_variations');
                $form.trigger('woocommerce_variation_select_change');
                $form.find('select[name^="attribute_"]').trigger('change');
            }, 150);
            return;
        }

        if (!Object.keys(requested).length) return;

        const $selects = $form.find('select[name^="attribute_"]');
        if (!$selects.length) return;

        $selects.each(function () {
            const $select = $(this);
            const name = ($select.attr('name') || '').replace(/^attribute_/, '');
            const wanted = requested[name];
            if (!wanted) return;

            $select.find('option').each(function () {
                const val = $(this).val();
                if (val && val === wanted) {
                    $select.val(val);
                }
            });
        });

        setTimeout(function () {
            const matchedVariation = findMatchingVariation($form, requested);
            if (matchedVariation) {
                applyVariationToForm($form, matchedVariation);
            }

            $form.trigger('check_variations');
            $form.trigger('woocommerce_variation_select_change');
            $form.find('select[name^="attribute_"]').trigger('change');
        }, 150);
    }

    $('.variations_form').each(function () {
        const $form = $(this);
        let isPreloadingFromUrl = currentUrlHasVariationPath();

        maybeMoveVariationFormByAcf($form);

        initButtonMode($form);
        preloadVariationFromPath($form);

        $form.on('found_variation', function (event, variation) {
            isPreloadingFromUrl = false;
            renderVariationInfo(variation);
            updateUrlFromVariation(variation);
            syncButtonsWithSelect($form);
        });

        $form.on('reset_data hide_variation', function () {
            clearVariationInfo();
            syncButtonsWithSelect($form);
            if (isPreloadingFromUrl && currentUrlHasVariationPath()) {
                return;
            }
            window.history.replaceState({}, '', cleanProductBaseUrl());
        });

        $form.on('woocommerce_update_variation_values', function () {
            syncButtonsWithSelect($form);
        });
    });
});
