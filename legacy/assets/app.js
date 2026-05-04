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

        nav.querySelectorAll('.nav-link').forEach((link) => {
            link.addEventListener('click', () => {
                nav.classList.remove('nav-open');
                navToggle.setAttribute('aria-expanded', 'false');
                navToggle.innerHTML = '&#9776;';
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !nav.classList.contains('nav-open')) {
                return;
            }
            nav.classList.remove('nav-open');
            navToggle.setAttribute('aria-expanded', 'false');
            navToggle.innerHTML = '&#9776;';
            navToggle.focus();
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

    const countUpValues = () => {
        const values = document.querySelectorAll('.hero-panel .stat-value');
        if (!values.length) {
            return;
        }

        const formatNumber = (value) => new Intl.NumberFormat().format(Math.round(value));
        values.forEach((value) => {
            const target = Number((value.textContent || '').replace(/[^\d]/g, ''));
            if (!Number.isFinite(target)) {
                return;
            }
            value.dataset.countTarget = String(target);
            value.textContent = '0';
        });

        const runCounter = (value, index = 0) => {
            if (value.dataset.counted === 'true') {
                return;
            }

            const target = Number(value.dataset.countTarget || '0');
            if (!Number.isFinite(target)) {
                return;
            }

            value.dataset.counted = 'true';
            window.setTimeout(() => {
                value.classList.add('is-counting');
                const duration = 1350;
                const start = performance.now();

                const tick = (now) => {
                    const progress = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 4);
                    value.textContent = formatNumber(target * eased);
                    if (progress < 1) {
                        window.requestAnimationFrame(tick);
                        return;
                    }
                    value.textContent = formatNumber(target);
                    value.classList.remove('is-counting');
                    value.classList.add('counted');
                };

                window.requestAnimationFrame(tick);
            }, 220 + (index * 180));
        };

        if (!('IntersectionObserver' in window)) {
            values.forEach(runCounter);
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }
                entry.target.querySelectorAll('.stat-value').forEach(runCounter);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.2 });

        document.querySelectorAll('.hero-panel').forEach((panel) => observer.observe(panel));
    };

    countUpValues();

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

    const initEditorWork = () => {
        initRichTextEditors();
        initQuillEditors();
    };

    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(initEditorWork, { timeout: 900 });
    } else {
        window.setTimeout(initEditorWork, 1);
    }

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

        if (latestNotificationId > 0 && previousNotificationId > 0 && latestNotificationId > previousNotificationId && !document.hidden) {
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
        if (document.hidden) {
            return;
        }

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

        window.setInterval(() => refreshServiceChat(chat), 5000);
    });

    document.querySelectorAll('[data-career-quiz]').forEach((quiz) => {
        const questions = [
            {
                category: 'Interview',
                question: 'What is the best thing to do before an interview?',
                help: 'Choose the answer that shows preparation and confidence.',
                answers: ['Arrive and improvise everything', 'Research the company and prepare examples', 'Only ask about salary'],
                correct: 1,
                action: 'Prepare two examples from your work, study, or projects before the interview.'
            },
            {
                category: 'CV',
                question: 'Which CV detail helps recruiters contact you faster?',
                help: 'Think about the first practical information a recruiter needs.',
                answers: ['A clear phone number and email', 'A very long paragraph', 'A hidden social profile only'],
                correct: 0,
                action: 'Check that your phone, email, location, and CV file are easy to find.'
            },
            {
                category: 'Profile',
                question: 'What makes a skill stronger on your profile?',
                help: 'A skill is stronger when it is connected to proof.',
                answers: ['Listing every skill you have heard of', 'Showing where you used it', 'Writing it in all capital letters'],
                correct: 1,
                action: 'Add real examples beside your strongest skills, like projects, tools, or work tasks.'
            },
            {
                category: 'Applications',
                question: 'After applying for a job, what should you track?',
                help: 'Good job searching is not only sending applications.',
                answers: ['Application status and next step', 'Only the company logo', 'Nothing until someone calls'],
                correct: 0,
                action: 'Use your dashboard to follow application progress and prepare for the next stage.'
            },
            {
                question: 'What is the best way to answer “Tell me about yourself”?',
                help: 'The strongest answer is short, relevant, and connected to the role.',
                answers: ['Share your full life story', 'Give a short summary of skills and goals', 'Say you do not know'],
                correct: 1,
                action: 'Write a 30-second introduction that connects your background to the job you want.'
            },
            {
                category: 'Job Match',
                question: 'Which job should you apply to first?',
                help: 'A good match saves time for you and the employer.',
                answers: ['Any job with a nice title', 'A role matching your skills and location', 'Only jobs with no description'],
                correct: 1,
                action: 'Compare job requirements with your skills before applying.'
            },
            {
                category: 'Applications',
                question: 'What should your cover note focus on?',
                help: 'A cover note should be useful, not generic.',
                answers: ['Why you fit this specific job', 'A copy of your CV', 'One sentence saying hello only'],
                correct: 0,
                action: 'Mention one or two reasons you fit the role before submitting.'
            },
            {
                category: 'Mindset',
                question: 'What is a professional response to rejection?',
                help: 'Rejection can still help your career.',
                answers: ['Ignore every future opportunity', 'Ask politely for feedback and keep improving', 'Send an angry message'],
                correct: 1,
                action: 'Use rejection as feedback. Improve your CV, skills, or interview answers.'
            },
            {
                category: 'Planning',
                question: 'Why should you save interesting jobs?',
                help: 'Saving helps you compare and plan.',
                answers: ['To compare roles before applying', 'To avoid applying forever', 'To hide them from others'],
                correct: 0,
                action: 'Save roles you like, then compare salary, location, requirements, and company.'
            },
            {
                category: 'Profile',
                question: 'What is the best next step when your profile score is low?',
                help: 'A complete profile makes matching easier.',
                answers: ['Leave it empty', 'Add missing profile details and upload CV', 'Delete your account'],
                correct: 1,
                action: 'Complete your profile details, skills, and CV before applying to more jobs.'
            }
        ];
        questions[4].category = 'Interview';
        questions[4].question = 'What is the best way to answer "Tell me about yourself"?';

        const questionEl = quiz.querySelector('[data-quiz-question]');
        const helpEl = quiz.querySelector('[data-quiz-help]');
        const answersEl = quiz.querySelector('[data-quiz-answers]');
        const progressEl = quiz.querySelector('[data-quiz-progress]');
        const progressBar = quiz.querySelector('[data-quiz-progress-bar]');
        const feedback = quiz.querySelector('[data-quiz-feedback]');
        const feedbackText = quiz.querySelector('[data-quiz-feedback-text]');
        const result = quiz.querySelector('[data-quiz-result]');
        const scoreEl = quiz.querySelector('[data-quiz-score]');
        const summaryEl = quiz.querySelector('[data-quiz-summary]');
        const breakdownEl = quiz.querySelector('[data-quiz-breakdown]');
        const reviewEl = quiz.querySelector('[data-quiz-review]');
        const prevButton = quiz.querySelector('[data-quiz-prev]');
        const nextButton = quiz.querySelector('[data-quiz-next]');
        const restartButton = quiz.querySelector('[data-quiz-restart]');
        const copyButton = quiz.querySelector('[data-quiz-copy]');
        const copyStatus = quiz.querySelector('[data-quiz-copy-status]');
        let current = 0;
        const chosen = Array(questions.length).fill(null);
        let latestResultText = '';

        const resultText = (score) => {
            if (score >= 8) {
                return 'Excellent. You are ready to apply with confidence. Keep your profile fresh and track every application.';
            }
            if (score >= 5) {
                return 'Good start. You understand the basics, but you should improve your CV, examples, and interview preparation.';
            }
            return 'You need more preparation before applying. Start with your CV, profile details, and basic interview answers.';
        };

        const showResult = () => {
            const score = chosen.reduce((total, answer, index) => total + (answer === questions[index].correct ? 1 : 0), 0);
            const categories = questions.reduce((items, item, index) => {
                const key = item.category || 'General';
                if (!items[key]) {
                    items[key] = { correct: 0, total: 0 };
                }
                items[key].total += 1;
                if (chosen[index] === item.correct) {
                    items[key].correct += 1;
                }
                return items;
            }, {});
            const weakAreas = Object.keys(categories).filter((key) => categories[key].correct < categories[key].total);
            questionEl.textContent = 'Quiz completed';
            helpEl.textContent = 'Here is your final result.';
            answersEl.innerHTML = '';
            feedback.hidden = true;
            result.hidden = false;
            scoreEl.textContent = score + ' / ' + questions.length;
            summaryEl.textContent = resultText(score);
            if (weakAreas.length > 0) {
                summaryEl.textContent += ' Focus next on: ' + weakAreas.slice(0, 3).join(', ') + '.';
            }
            latestResultText = 'KDXJobs Job Readiness Quiz: ' + score + '/' + questions.length + '. ' + summaryEl.textContent;
            if (breakdownEl) {
                breakdownEl.innerHTML = '';
                Object.keys(categories).forEach((category) => {
                    const item = categories[category];
                    const card = document.createElement('div');
                    const label = document.createElement('span');
                    const value = document.createElement('strong');
                    const bar = document.createElement('i');
                    card.className = 'quiz-breakdown-item';
                    label.textContent = category;
                    value.textContent = item.correct + ' / ' + item.total;
                    bar.style.width = ((item.correct / item.total) * 100) + '%';
                    card.appendChild(label);
                    card.appendChild(value);
                    card.appendChild(bar);
                    breakdownEl.appendChild(card);
                });
            }
            if (reviewEl) {
                reviewEl.innerHTML = '';
                questions.forEach((item, index) => {
                    const row = document.createElement('div');
                    const isCorrect = chosen[index] === item.correct;
                    const title = document.createElement('strong');
                    const answer = document.createElement('span');
                    const correct = document.createElement('span');
                    row.className = 'quiz-review-item ' + (isCorrect ? 'is-correct' : 'is-wrong');
                    title.textContent = (index + 1) + '. ' + item.question;
                    answer.textContent = 'Your answer: ' + item.answers[chosen[index]];
                    correct.textContent = isCorrect ? 'Correct' : 'Best answer: ' + item.answers[item.correct];
                    row.appendChild(title);
                    row.appendChild(answer);
                    row.appendChild(correct);
                    reviewEl.appendChild(row);
                });
            }
            progressEl.textContent = 'Final result';
            progressBar.style.width = '100%';
            prevButton.hidden = true;
            nextButton.hidden = true;
        };

        const render = () => {
            const item = questions[current];
            questionEl.textContent = item.question;
            helpEl.textContent = item.help;
            progressEl.textContent = 'Question ' + (current + 1) + ' of ' + questions.length;
            progressBar.style.width = ((current + 1) / questions.length * 100) + '%';
            result.hidden = true;
            feedback.hidden = chosen[current] === null;
            feedbackText.textContent = chosen[current] === null ? '' : item.action;
            prevButton.hidden = current === 0;
            nextButton.hidden = false;
            nextButton.textContent = current === questions.length - 1 ? 'See Result' : 'Next';
            nextButton.disabled = chosen[current] === null;
            answersEl.innerHTML = '';

            item.answers.forEach((answer, index) => {
                const label = document.createElement('label');
                if (chosen[current] === index) {
                    label.classList.add(index === item.correct ? 'is-correct' : 'is-wrong');
                }
                const input = document.createElement('input');
                input.type = 'radio';
                input.name = 'career_quiz_' + current;
                input.checked = chosen[current] === index;
                input.addEventListener('change', () => {
                    chosen[current] = index;
                    render();
                });
                label.appendChild(input);
                label.appendChild(document.createTextNode(answer));
                answersEl.appendChild(label);
            });
        };

        nextButton.addEventListener('click', () => {
            if (chosen[current] === null) {
                return;
            }
            if (current === questions.length - 1) {
                showResult();
                return;
            }
            current += 1;
            render();
        });

        prevButton.addEventListener('click', () => {
            current = Math.max(0, current - 1);
            render();
        });

        if (restartButton) {
            restartButton.addEventListener('click', () => {
                current = 0;
                chosen.fill(null);
                latestResultText = '';
                if (copyStatus) {
                    copyStatus.textContent = '';
                }
                render();
            });
        }

        if (copyButton) {
            copyButton.addEventListener('click', async () => {
                if (!latestResultText) {
                    return;
                }
                try {
                    await navigator.clipboard.writeText(latestResultText);
                    if (copyStatus) {
                        copyStatus.textContent = 'Copied';
                    }
                } catch (error) {
                    if (copyStatus) {
                        copyStatus.textContent = 'Copy unavailable';
                    }
                }
            });
        }

        render();
    });
}());
