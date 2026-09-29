import { FieldError } from '../../Components/Ui';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({ email: '', password: '' });

    function submit(event) {
        event.preventDefault();
        post('/login', { onFinish: () => setData('password', '') });
    }

    return (
        <>
            <Head title="Masuk" />
            <main className="grid min-h-screen bg-zinc-100 lg:grid-cols-[minmax(0,1fr)_460px]">
                <section className="hidden bg-zinc-900 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                    <p className="text-sm font-semibold text-emerald-400">Secure Document Signature</p>
                    <div className="max-w-xl">
                        <p className="text-sm font-semibold text-emerald-400">Keamanan dokumen</p>
                        <h1 className="mt-3 text-4xl font-semibold">Tanda tangani dan periksa integritas dokumen.</h1>
                        <p className="mt-4 max-w-lg text-base leading-7 text-zinc-300">
                            Akses terkontrol untuk administrator dan penandatangan dokumen digital.
                        </p>
                    </div>
                    <Link href="/verify" className="w-fit text-sm font-semibold text-zinc-300 hover:text-white hover:underline">
                        Verifikasi dokumen publik
                    </Link>
                </section>

                <section className="flex items-center justify-center px-4 py-10 sm:px-8">
                    <div className="w-full max-w-sm">
                        <div className="mb-7 lg:hidden">
                            <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                        </div>
                        <div className="ui-card p-6 sm:p-8">
                            <h2 className="text-2xl font-semibold text-zinc-950">Masuk</h2>
                            <p className="mt-2 text-sm leading-6 text-zinc-600">
                                Gunakan akun administrator atau penandatangan Anda.
                            </p>

                            <form className="mt-7 flex flex-col gap-5" onSubmit={submit}>
                                <label className="flex flex-col gap-2 text-sm font-medium" htmlFor="email">
                                    Email
                                    <input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(event) => setData('email', event.target.value)}
                                        autoComplete="email"
                                        autoFocus
                                        required
                                        className="ui-input"
                                    />
                                    <FieldError>{errors.email}</FieldError>
                                </label>

                                <label className="flex flex-col gap-2 text-sm font-medium" htmlFor="password">
                                    Password
                                    <input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(event) => setData('password', event.target.value)}
                                        autoComplete="current-password"
                                        required
                                        className="ui-input"
                                    />
                                    <FieldError>{errors.password}</FieldError>
                                </label>

                                <button type="submit" disabled={processing} className="ui-button-primary w-full">
                                    {processing ? 'Memproses...' : 'Masuk'}
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
            </main>
        </>
    );
}
