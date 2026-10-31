import { usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

/**
 * Arabic ⇄ English: saved on the server (locale.switch) and applied without a
 * page reload. Used by LanguageSwitcher and the top bar's user menu.
 */
export function useLanguage() {
    const page = usePage();
    const { locale } = useI18n();

    const toggleLanguage = () => {
        const nextLocale = page.props.locale === 'ar' ? 'en' : 'ar';
        router.post(route('locale.switch'), { locale: nextLocale }, {
            preserveScroll: true,
            onSuccess: () => {
                locale.value = nextLocale;
                document.documentElement.lang = nextLocale;
                document.documentElement.dir = nextLocale === 'ar' ? 'rtl' : 'ltr';
            }
        });
    };

    return { toggleLanguage };
}
