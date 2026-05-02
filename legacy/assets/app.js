(function () {
    const themeButtons = document.querySelectorAll('[data-theme-toggle]');
    const nav = document.querySelector('.nav');
    const navToggle = document.querySelector('[data-nav-toggle]');
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    const setTheme = (theme) => {
        const isDark = theme === 'dark';
        document.documentElement.classList.toggle('theme-dark', isDark);
        document.body.classList.toggle('theme-dark', isDark);
        try {
            localStorage.setItem('kdxjobs-theme', isDark ? 'dark' : 'light');
        } catch (error) {
            console.warn('Theme preference could not be saved.', error);
        }

        themeButtons.forEach((button) => {
            const icon = button.querySelector('[data-theme-icon]');
            const label = button.querySelector('[data-theme-label]');
            button.setAttribute('aria-label', isDark ? 'Switch to normal mode' : 'Switch to dark mode');
            button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            button.setAttribute('title', isDark ? 'Switch to normal mode' : 'Switch to dark mode');
            if (icon) {
                icon.innerHTML = isDark ? '&#9788;' : '&#9790;';
            }
            if (label) {
                label.textContent = isDark ? 'Normal mode' : 'Dark mode';
            }
        });
    };

    try {
        setTheme(localStorage.getItem('kdxjobs-theme') === 'dark' ? 'dark' : 'light');
    } catch (error) {
        setTheme('light');
    }

    if (nav && navToggle) {
        navToggle.addEventListener('click', () => {
            const isOpen = nav.classList.toggle('nav-open');
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            navToggle.innerHTML = isOpen ? '&times;' : '&#9776;';
        });
    }

    themeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            setTheme(document.body.classList.contains('theme-dark') ? 'light' : 'dark');
        });
    });

    document.querySelectorAll('[data-auto-submit]').forEach((field) => {
        field.addEventListener('change', () => {
            if (field.form) {
                field.form.submit();
            }
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.dataset.confirm || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-file-name-target]').forEach((input) => {
        const wrap = input.closest('.cv-upload');
        const label = wrap ? wrap.querySelector('[data-file-name]') : null;
        if (!label) {
            return;
        }

        input.addEventListener('change', () => {
            label.textContent = input.files && input.files.length ? input.files[0].name : 'Choose a PDF file';
        });
    });

    document.querySelectorAll('[data-image-preview-target]').forEach((input) => {
        const preview = document.getElementById(input.dataset.imagePreviewTarget || '');
        if (!preview) {
            return;
        }

        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file) {
                return;
            }

            const reader = new FileReader();
            reader.addEventListener('load', () => {
                preview.src = reader.result;
                preview.classList.add('has-image');
            });
            reader.readAsDataURL(file);
        });
    });

    document.querySelectorAll('[data-profile-tools]').forEach((panel) => {
        const status = panel.querySelector('[data-profile-tool-status]');
        const setStatus = (message) => {
            if (!status) {
                return;
            }
            status.textContent = message;
            window.setTimeout(() => {
                status.textContent = '';
            }, 2500);
        };

        const copyButton = panel.querySelector('[data-copy-profile]');
        if (copyButton) {
            copyButton.addEventListener('click', async () => {
                const summary = panel.dataset.profileSummary || '';
                if (!summary.trim()) {
                    setStatus('Nothing to copy yet.');
                    return;
                }

                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(summary);
                    } else {
                        const textarea = document.createElement('textarea');
                        textarea.value = summary;
                        textarea.setAttribute('readonly', '');
                        textarea.style.position = 'fixed';
                        textarea.style.left = '-9999px';
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        textarea.remove();
                    }
                    setStatus('Profile summary copied.');
                } catch (error) {
                    console.warn('Profile summary copy failed.', error);
                    setStatus('Copy failed. Please try again.');
                }
            });
        }

        const printButton = panel.querySelector('[data-print-profile]');
        if (printButton) {
            printButton.addEventListener('click', () => {
                window.print();
            });
        }
    });

    const registerActionField = document.querySelector('form input[name="action"][value="register"]');
    const jobSeekerForm = registerActionField ? registerActionField.form : null;
    const companyForm = document.getElementById('company-form');
    document.querySelectorAll('[data-register-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!jobSeekerForm || !companyForm) {
                return;
            }
            const target = button.dataset.registerToggle;
            if (target === 'company') {
                companyForm.classList.remove('hidden');
                jobSeekerForm.classList.add('hidden');
            } else if (target === 'jobseeker') {
                companyForm.classList.add('hidden');
                jobSeekerForm.classList.remove('hidden');
            }
        });
    });

    const isRequiredEditorField = (source) => ['description', 'content'].includes(source.name);

    const initQuillEditors = () => {
        document.querySelectorAll('[data-quill-editor]').forEach((wrap) => {
            const source = wrap.querySelector('.rich-editor-source');
            const host = wrap.querySelector('.quill-editor');
            if (!source || !host || !window.Quill) {
                return;
            }

            const requiredEditor = isRequiredEditorField(source);
            source.required = false;

            const toolbarOptions = [
                [{ header: [2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['blockquote', 'link', 'image'],
                ['clean']
            ];

            const quill = new window.Quill(host, {
                theme: 'snow',
                placeholder: host.dataset.placeholder || '',
                modules: {
                    toolbar: {
                        container: toolbarOptions,
                        handlers: {
                            image() {
                                const input = document.createElement('input');
                                input.type = 'file';
                                input.accept = 'image/png,image/jpeg,image/webp';
                                input.addEventListener('change', () => {
                                    const file = input.files && input.files[0];
                                    if (!file) {
                                        return;
                                    }
                                    if (file.size > 2 * 1024 * 1024) {
                                        window.alert('Please choose an image smaller than 2 MB.');
                                        return;
                                    }

                                    const data = new FormData();
                                    data.append('action', 'upload_blog_editor_image');
                                    data.append('csrf_token', csrfToken);
                                    data.append('editor_image', file);

                                    fetch(window.location.href, {
                                        method: 'POST',
                                        body: data,
                                        headers: { 'Accept': 'application/json' }
                                    })
                                        .then((response) => response.json())
                                        .then((result) => {
                                            if (!result.ok || !result.url) {
                                                throw new Error(result.error || 'Could not upload image.');
                                            }
                                            const range = quill.getSelection(true);
                                            quill.insertEmbed(range.index, 'image', result.url, 'user');
                                            quill.setSelection(range.index + 1, 0);
                                            sync();
                                        })
                                        .catch((error) => {
                                            window.alert(error.message || 'Could not upload image.');
                                        });
                                });
                                input.click();
                            }
                        }
                    }
                }
            });

            if (source.value.trim() !== '') {
                quill.clipboard.dangerouslyPasteHTML(source.value);
            }

            const sync = () => {
                const html = quill.root.innerHTML.trim();
                source.value = html === '<p><br></p>' ? '' : html;
                wrap.classList.remove('is-invalid');
            };

            quill.on('text-change', sync);
            sync();

            const form = wrap.closest('form');
            if (!form) {
                return;
            }

            form.addEventListener('submit', (event) => {
                sync();
                if (isRequiredEditorField(source) && quill.getText().trim() === '') {
                    event.preventDefault();
                    wrap.classList.add('is-invalid');
                    quill.focus();
                }
            });
        });
    };

    const ckeditorFallbackInit = () => {
        document.querySelectorAll('[data-editor]').forEach((wrap) => {
            const editor = wrap.querySelector('.rich-editor');
            const source = wrap.querySelector('.rich-editor-source');
            if (!editor || !source) {
                return;
            }

            if (source.value.trim() !== '' && editor.innerHTML.trim() === '') {
                editor.innerHTML = source.value;
            }

            const sync = () => {
                source.value = editor.innerHTML.trim();
            };

            wrap.querySelectorAll('[data-command]').forEach((button) => {
                button.addEventListener('click', () => {
                    editor.focus();
                    document.execCommand(button.dataset.command, false, button.dataset.value || null);
                    sync();
                });
            });

            editor.addEventListener('input', () => {
                editor.style.boxShadow = '';
                sync();
            });
            editor.addEventListener('blur', sync);
            editor.addEventListener('paste', (event) => {
                event.preventDefault();
                const text = (event.clipboardData || window.clipboardData).getData('text/plain');
                document.execCommand('insertText', false, text);
                sync();
            });

            const form = wrap.closest('form');
            if (form) {
                form.addEventListener('submit', (event) => {
                    sync();
                    if (isRequiredEditorField(source) && editor.textContent.trim() === '') {
                        event.preventDefault();
                        editor.focus();
                        editor.style.boxShadow = '0 0 0 4px #fee2e2 inset';
                    }
                });
            }
        });
    };

    const initRichTextEditors = () => {
        const wraps = document.querySelectorAll('[data-editor]');
        if (!wraps.length) {
            return;
        }

        if (!window.ClassicEditor) {
            ckeditorFallbackInit();
            return;
        }

        wraps.forEach((wrap, index) => {
            const legacyEditor = wrap.querySelector('.rich-editor');
            const source = wrap.querySelector('.rich-editor-source');
            const toolbar = wrap.querySelector('.editor-toolbar');
            if (!source) {
                return;
            }

            if (!source.id) {
                source.id = 'rich-editor-source-' + (index + 1);
            }

            const requiredEditor = isRequiredEditorField(source);
            source.required = false;
            source.style.position = 'static';
            source.style.left = 'auto';
            source.style.width = '100%';
            source.style.height = 'auto';
            source.style.opacity = '1';
            source.classList.add('textarea');

            if (legacyEditor) {
                legacyEditor.remove();
            }
            if (toolbar) {
                toolbar.remove();
            }

            const host = document.createElement('div');
            host.className = 'ck-editor-host';
            source.parentNode.insertBefore(host, source);
            host.appendChild(source);

            window.ClassicEditor.create(source, {
                toolbar: [
                    'heading',
                    '|',
                    'bold',
                    'italic',
                    '|',
                    'bulletedList',
                    'numberedList',
                    '|',
                    'blockQuote',
                    'link',
                    '|',
                    'undo',
                    'redo'
                ]
            }).then((instance) => {
                const form = wrap.closest('form');
                if (!form) {
                    return;
                }

                form.addEventListener('submit', (event) => {
                    instance.updateSourceElement();
                    if (requiredEditor && source.value.trim() === '') {
                        event.preventDefault();
                        instance.editing.view.focus();
                    }
                });
            }).catch((error) => {
                console.error('CKEditor failed to load for rich text editor.', error);
                if (host.parentNode) {
                    host.parentNode.insertBefore(source, host);
                    host.remove();
                }
                if (toolbar) {
                    wrap.insertBefore(toolbar, source);
                }
                if (legacyEditor) {
                    wrap.insertBefore(legacyEditor, source);
                }
                ckeditorFallbackInit();
            });
        });
    };

    initRichTextEditors();
    initQuillEditors();

    document.querySelectorAll('form').forEach((form) => {
        const select = form.querySelector('[data-cv-option]');
        const uploadWrap = form.querySelector('[data-cv-upload]');
        const savedNote = form.querySelector('[data-cv-saved-note]');
        const uploadInput = uploadWrap ? uploadWrap.querySelector('input[type="file"]') : null;
        if (!select || !uploadWrap || !uploadInput) {
            return;
        }

        const syncCvOption = () => {
            const needsUpload = select.value === 'new';
            uploadWrap.style.display = needsUpload ? '' : 'none';
            if (savedNote) {
                savedNote.style.display = needsUpload ? 'none' : '';
            }
            uploadInput.required = needsUpload;
            if (!needsUpload) {
                uploadInput.value = '';
            }
        };

        select.addEventListener('change', syncCvOption);
        syncCvOption();
    });

    const playKdxSound = () => {
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) {
                return;
            }
            const audioContext = new AudioContextClass();
            const oscillator = audioContext.createOscillator();
            const gain = audioContext.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, audioContext.currentTime);
            gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.2, audioContext.currentTime + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.35);
            oscillator.connect(gain);
            gain.connect(audioContext.destination);
            oscillator.start();
            oscillator.stop(audioContext.currentTime + 0.36);
        } catch (error) {
            console.warn('Notification sound could not play.', error);
        }
    };

    const notificationWatch = document.getElementById('notification-watch');
    if (notificationWatch) {
        const latestNotificationId = Number(notificationWatch.dataset.latestNotificationId || '0');
        const storageKey = 'kdxjobs-latest-notification-id';
        const previousNotificationId = Number(localStorage.getItem(storageKey) || '0');

        if (latestNotificationId > 0 && previousNotificationId > 0 && latestNotificationId > previousNotificationId) {
            playKdxSound();
        }
        if (latestNotificationId > 0) {
            localStorage.setItem(storageKey, String(latestNotificationId));
        }
    }

    const refreshServiceChat = async (chat, shouldSound = true) => {
        const applicationId = chat.dataset.applicationId;
        const body = chat.querySelector('.service-chat-body');
        if (!applicationId || !body) {
            return;
        }

        const previousLatest = Number(chat.dataset.latestMessageId || '0');
        try {
            const response = await fetch('index.php?ajax=service_chat&application_id=' + encodeURIComponent(applicationId) + '&latest=' + previousLatest, {
                headers: { 'X-Requested-With': 'fetch' }
            });
            if (!response.ok) {
                return;
            }
            const payload = await response.json();
            if (!payload.ok) {
                return;
            }
            const nextLatest = Number(payload.latest_id || '0');
            if (nextLatest !== previousLatest) {
                body.innerHTML = payload.html;
                chat.dataset.latestMessageId = String(nextLatest);
                body.scrollTop = body.scrollHeight;
                if (shouldSound && previousLatest > 0 && nextLatest > previousLatest) {
                    playKdxSound();
                }
            }
        } catch (error) {
            console.warn('Service chat refresh failed.', error);
        }
    };

    document.querySelectorAll('[data-service-chat]').forEach((chat) => {
        const body = chat.querySelector('.service-chat-body');
        if (body) {
            body.scrollTop = body.scrollHeight;
        }

        const form = chat.querySelector('.service-chat-form');
        if (form) {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const message = form.querySelector('[name="chat_message"]');
                if (!message || message.value.trim() === '') {
                    return;
                }
                const formData = new FormData(form);
                formData.append('ajax', '1');
                const button = form.querySelector('button');
                if (button) {
                    button.disabled = true;
                }
                try {
                    const response = await fetch('index.php', {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'fetch' }
                    });
                    const payload = await response.json();
                    if (payload.ok) {
                        const nextBody = chat.querySelector('.service-chat-body');
                        if (nextBody) {
                            nextBody.innerHTML = payload.html;
                            nextBody.scrollTop = nextBody.scrollHeight;
                        }
                        chat.dataset.latestMessageId = String(payload.latest_id || chat.dataset.latestMessageId || '0');
                        message.value = '';
                    }
                } catch (error) {
                    console.warn('Service chat send failed.', error);
                    form.submit();
                } finally {
                    if (button) {
                        button.disabled = false;
                    }
                }
            });
        }

        window.setInterval(() => refreshServiceChat(chat), 3000);
    });
}());
