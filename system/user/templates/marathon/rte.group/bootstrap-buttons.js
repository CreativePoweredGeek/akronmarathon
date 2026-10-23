RedactorX.add('plugin', 'bootstrapbuttons', {
    styles: {
        normal: '',
        primary: 'btn btn-primary',
        secondary: 'btn btn-secondary',
        dark: 'btn btn-dark',
        outlinePrimary: 'btn btn-outline-primary',
        outlineSecondary: 'btn btn-outline-secondary',
        outlineDark: 'btn btn-outline-dark'
    },

    subscribe: {
        'popup.before.open': function () {
            if (this.app.popup.getName() !== 'link') {
                return;
            }

            var popup = this.app.popup.getStack();
            var form = popup.get('form');
            if (!form || !form.url) {
                return;
            }

            var $link = this.app.link.getLink();
            var options = {
                normal: 'Normal link',
                primary: 'Primary button',
                secondary: 'Secondary button',
                dark: 'Dark button',
                outlinePrimary: 'Outline primary button',
                outlineSecondary: 'Outline secondary button',
                outlineDark: 'Outline dark button'
            };

            this.originalClasses = $link.length ? ($link.attr('class') || '') : '';
            this.currentStyle = 'normal';

            if ($link.length) {
                Object.keys(this.styles).some(function (key) {
                    if (key !== 'normal' && $link.hasClass(this.styles[key])) {
                        this.currentStyle = key;
                        return true;
                    }
                    return false;
                }, this);

                // Preserve existing button variants outside the offered choices.
                if (this.currentStyle === 'normal' && $link.hasClass('btn')) {
                    options.keep = 'Keep current styling';
                    this.currentStyle = 'keep';
                }
            }

            // Add the select after URL, keeping every existing form field.
            var extendedForm = {};
            Object.keys(form).forEach(function (key) {
                if (key === 'bootstrapButtonStyle') {
                    return;
                }
                extendedForm[key] = form[key];
                if (key === 'url') {
                    extendedForm.bootstrapButtonStyle = {
                        type: 'select',
                        label: 'Button style',
                        options: options
                    };
                }
            });
            popup.set('form', extendedForm);

            // Keep the native insert/save actions and run them through our wrapper.
            var footer = popup.get('footer') || {};
            var extendedFooter = {};
            Object.keys(footer).forEach(function (key) {
                var item = footer[key];
                if (item && (item.command === 'link.insert' || item.command === 'link.save')) {
                    item = Object.assign({}, item, {
                        bootstrapbuttonsCommand: item.command,
                        command: 'bootstrapbuttons.save'
                    });
                }
                extendedFooter[key] = item;
            });
            popup.set('footer', extendedFooter);
        },

        'popup.open': function () {
            if (this.app.popup.getName() === 'link') {
                this.app.popup.getInput('bootstrapButtonStyle').val(this.currentStyle);
            }
        },

        'link.add, link.change': function (event) {
            if (this.saving) {
                this.savedLink = event.get('element');
            }
        }
    },

    save: function (popup, buttonName) {
        var item = (popup.get('footer') || {})[buttonName];
        var command = item && item.bootstrapbuttonsCommand;
        if (command !== 'link.insert' && command !== 'link.save') {
            return;
        }

        var data = popup.getData();
        var style = data.bootstrapButtonStyle;
        var keep = style === 'keep' && this.currentStyle === 'keep';
        if (!keep && !Object.prototype.hasOwnProperty.call(this.styles, style)) {
            this.app.popup.setFocus('bootstrapButtonStyle');
            return;
        }

        // Also respect a CSS classes field if the linkclasses plugin is enabled.
        var classValue = typeof data.classname === 'string'
            ? data.classname
            : this.originalClasses;
        var classes = (classValue || '').split(/\s+/).filter(function (name) {
            return name && (keep || (name !== 'btn' &&
                !/^btn-(?:outline-)?(?:primary|secondary|success|danger|warning|info|light|dark|link)$/.test(name)));
        });
        if (!keep && this.styles[style]) {
            classes.push(this.styles[style]);
        }

        this.app.popup.close();
        this.app.state.add();
        this.savedLink = null;
        this.saving = true;
        try {
            this.app.api(command);
        } finally {
            this.saving = false;
        }

        if (!this.savedLink || !this.savedLink.length) {
            return;
        }

        if (classes.length) {
            this.savedLink.attr('class', classes.join(' '));
        } else {
            this.savedLink.removeAttr('class');
        }
        this.app.sync.invoke();
    }
});
