import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    function submit(event) {
        event.preventDefault();
        post('/login', { onFinish: () => setData('password', '') });
    }

    return (
        <>
            <Head title="Login" />
            <main className="flex min-h-screen items-center justify-center bg-zinc-100 px-4 py-10">
                <section className="w-full max-w-sm border border-zinc-200 bg-white p-7 shadow-sm">
                    <div className="flex flex-col gap-2">
                        <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                        <h1 className="text-2xl font-semibold">Login</h1>
                        <p className="text-sm text-zinc-600">Masuk menggunakan akun administrator atau signer.</p>
                    </div>

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
                                className="h-11 border border-zinc-300 px-3 font-normal outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100"
                            />
                            {errors.email && <span className="font-normal text-red-700">{errors.email}</span>}
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
                                className="h-11 border border-zinc-300 px-3 font-normal outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100"
                            />
                            {errors.password && <span className="font-normal text-red-700">{errors.password}</span>}
                        </label>

                        <button
                            type="submit"
                            disabled={processing}
                            className="h-11 bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing ? 'Memproses...' : 'Login'}
                        </button>
                    </form>
                </section>
            </main>
        </>
    );
}
