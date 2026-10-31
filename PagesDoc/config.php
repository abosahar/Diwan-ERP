<?php

// PagesDoc settings. The home page is the project's README.md (what the system is); every docs/*.md becomes a
// guide; PagesDoc/pages/*.md are pages written for this site only (they may contain HTML and {{repo}},
// {{branch}}, {{version}}). A new .md file not listed in `pages` still gets a page (group «أخرى»).

return [
    'source' => __DIR__.'/../docs',
    'extra' => __DIR__.'/pages',
    'output' => __DIR__.'/site',

    // Every GitHub link of the pages opens this repository (the original project): the repository icon,
    // releases, tags, help requests, «عرض على GitHub», and files outside docs/ (on this branch).
    'repo' => 'turkialawlqy/Diwan-ERP',
    'branch' => 'main',

    // Wherever the docs name these repositories (links, shown addresses, install commands), the pages use `repo`,
    // e.g. a fork's own repository. The docs/*.md files themselves are not changed.
    'repo_aliases' => [],

    'site' => [
        'name' => 'الديوان',
        'tagline' => 'مركز التوثيق',
        'title' => 'توثيق نظام «الديوان»',
        'lede' => 'كل ما تحتاجه لتثبيت «الديوان» وتشغيله وتطويره: أدلة خطوة بخطوة لأجهزة المكتب والخوادم، ومرجع تقني للفوترة الإلكترونية والتصنيع والواجهة البرمجية.',
    ],

    // The home page: README.md, shown first so readers know the whole system before choosing a guide.
    'home' => [
        'source' => __DIR__.'/../README.md',
        'nav' => 'الرئيسية: نظرة عامة على النظام',
        'drop' => ['الوثائق'],              // the site's own navigation lists the guides
        'install' => 'التثبيت (مجاني)',     // this section's table becomes the install cards (`quick_start`)
        'modules' => 'الوحدات',             // this section's table becomes one card per module
        'module_icons' => [
            'المحاسبة' => 'calculator', 'المبيعات' => 'cart', 'المشتريات' => 'truck', 'المستودعات' => 'box',
            'نقاط البيع' => 'store', 'الفوترة الإلكترونية' => 'receipt', 'المقاولات' => 'building',
            'المعدات والصيانة' => 'wrench', 'قوالب الطباعة' => 'printer', 'الموارد البشرية' => 'users', 'المنصة' => 'blocks',
        ],
    ],

    // The page linked from the home page and the footer for suggestions and feedback.
    'feedback' => 'FEATURE-REQUESTS.md',

    'groups' => [
        'start' => [
            'title' => 'التثبيت والبدء',
            'lede' => 'تشغيل النظام على جهاز في المكتب خطوة بخطوة، دون خبرة تقنية.',
        ],
        'server' => [
            'title' => 'الخوادم والتشغيل',
            'lede' => 'لمن يشغّل النظام على خادم أو يقدّمه لعدة منشآت.',
        ],
        'modules' => [
            'title' => 'الوحدات والميزات',
            'lede' => 'شرح تفصيلي لوحدات النظام المتخصصة.',
        ],
        'dev' => [
            'title' => 'للمطوّرين',
            'lede' => 'الربط البرمجي وبنية النظام من الداخل.',
        ],
        'help' => [
            'title' => 'المساعدة والتواصل',
            'lede' => 'أرسل اقتراحاتك وملاحظاتك إلى مطوّري النظام.',
        ],
        'other' => [
            'title' => 'أخرى',
            'lede' => '',
        ],
    ],

    // Keyed by file name (docs/ or pages/), in display order.
    'pages' => [
        'INSTALL.md' => [
            'slug' => 'install', 'group' => 'start', 'nav' => 'من أين أبدأ؟', 'icon' => 'compass', 'audience' => 'الجميع',
            'summary' => 'قارن بين التثبيت على جهاز في المكتب أو على خادم، واعرف ما يحتاجه الجهاز وما يحدث أثناء التثبيت وبعده.',
        ],
        'INSTALL-WINDOWS.md' => [
            'slug' => 'install-windows', 'group' => 'start', 'nav' => 'ويندوز', 'icon' => 'desktop', 'audience' => 'أصحاب المنشآت',
            'summary' => 'ملف واحد تنقر عليه يثبّت النظام على ويندوز 10 أو 11، مع أدوات قائمة ابدأ والنقل إلى جهاز جديد وحل المشكلات.',
        ],
        'INSTALL-MAC.md' => [
            'slug' => 'install-mac', 'group' => 'start', 'nav' => 'ماك', 'icon' => 'laptop', 'audience' => 'أصحاب المنشآت',
            'summary' => 'سطر واحد في Terminal يثبّت النظام على ماك، ويشغّله تلقائياً كلما سجّلت الدخول.',
        ],
        'INSTALL-LINUX.md' => [
            'slug' => 'install-linux', 'group' => 'start', 'nav' => 'لينكس', 'icon' => 'terminal', 'audience' => 'أصحاب المنشآت',
            'summary' => 'تثبيت بسطر واحد على Ubuntu أو Mint أو Debian، مع أوامر التشغيل اليومي وأماكن الملفات.',
        ],
        'WINDOWS-LOCAL.md' => [
            'slug' => 'windows-local', 'group' => 'start', 'nav' => 'ويندوز القديم (MAMP)', 'icon' => 'history', 'audience' => 'النسخ القديمة',
            'summary' => 'للنسخ المثبّتة سابقاً على MAMP أو Laragon: سكربت التحديث، والتحديث التلقائي، والاستعادة.',
        ],
        'SERVER-SETUP.md' => [
            'slug' => 'server-setup', 'group' => 'server', 'nav' => 'إعداد خادم من الصفر', 'icon' => 'server', 'audience' => 'مديرو الخوادم',
            'summary' => 'من إنشاء خادم DigitalOcean وربط النطاق وشهادة SSL حتى تسجيل أول منشأة، مع المراقبة وحل المشكلات.',
        ],
        'DEPLOYMENT.md' => [
            'slug' => 'deployment', 'group' => 'server', 'nav' => 'النشر والتشغيل', 'icon' => 'cloud', 'audience' => 'مديرو الخوادم والمطوّرون',
            'summary' => 'مرجع تقني: المتطلبات، والإعدادات، والطوابير والمجدول، والنسخ الاحتياطي والاستعادة، والتحقق الثنائي.',
        ],
        'DATA-RETENTION.md' => [
            'slug' => 'data-retention', 'group' => 'server', 'nav' => 'الاحتفاظ بالبيانات', 'icon' => 'shield', 'audience' => 'مدير المنصة',
            'summary' => 'مدد الاحتفاظ بالبيانات، وما يحدث عند انتهاء الاشتراك أو مغادرة المنشأة، وطلبات أصحاب البيانات.',
        ],
        'ZATCA.md' => [
            'slug' => 'zatca', 'group' => 'modules', 'nav' => 'الفوترة الإلكترونية', 'icon' => 'receipt', 'audience' => 'المحاسبون والمطوّرون',
            'summary' => 'ربط الفوترة الإلكترونية بمنصة «فاتورة» في مرحلتيها، وتفاصيل التوقيع والإرسال والبيئات وملف PDF/A-3.',
        ],
        'MANUFACTURING.md' => [
            'slug' => 'manufacturing', 'group' => 'modules', 'nav' => 'التصنيع والمصانع', 'icon' => 'factory', 'audience' => 'المصانع والمحاسبون',
            'summary' => 'أوامر الإنتاج والتصنيع المستمر، وقيودهما المحاسبية، وحساب التكلفة، وتخطيط الاحتياجات والجودة.',
        ],
        'API.md' => [
            'slug' => 'api', 'group' => 'dev', 'nav' => 'الواجهة البرمجية API', 'icon' => 'code', 'audience' => 'المطوّرون والتكاملات',
            'summary' => 'واجهة REST v1: المصادقة والمسارات والأخطاء، وأحداث Webhooks والتحقق من توقيعها.',
        ],
        'ARCHITECTURE.md' => [
            'slug' => 'architecture', 'group' => 'dev', 'nav' => 'المعمارية', 'icon' => 'blocks', 'audience' => 'المطوّرون',
            'summary' => 'تعدد المنشآت، والصلاحيات، ومحرك القيود وقوالبه، والمخزون والترقيم داخل الشيفرة.',
        ],
        'FEATURE-REQUESTS.md' => [
            'slug' => 'feature-requests', 'group' => 'help', 'nav' => 'الاقتراحات والملاحظات', 'icon' => 'bulb', 'audience' => 'كل المستخدمين',
            'summary' => 'كيف ترسل فكرة ميزة أو تحسين من صفحة «طلبات المميزات» في النظام إلى مطوّري المشروع على GitHub، وتتابعها.',
        ],
    ],

    // Install cards in the home page's «التثبيت» section (README's table, one card per guide).
    'quick_start' => [
        ['page' => 'INSTALL-WINDOWS.md', 'title' => 'جهاز ويندوز', 'how' => 'ملف واحد تنقر عليه', 'time' => '10-20 دقيقة', 'level' => 'سهل', 'icon' => 'desktop'],
        ['page' => 'INSTALL-MAC.md', 'title' => 'جهاز ماك', 'how' => 'سطر واحد تلصقه في Terminal', 'time' => '10-20 دقيقة', 'level' => 'سهل', 'icon' => 'laptop'],
        ['page' => 'INSTALL-LINUX.md', 'title' => 'جهاز لينكس', 'how' => 'سطر واحد تلصقه في الطرفية', 'time' => '5-15 دقيقة', 'level' => 'سهل', 'icon' => 'terminal'],
        ['page' => 'SERVER-SETUP.md', 'title' => 'خادم على الإنترنت', 'how' => 'عدة منشآت بنطاقات فرعية، ويحتاج نطاقاً', 'time' => 'ساعة تقريباً', 'level' => 'متوسط', 'icon' => 'server'],
    ],
];
