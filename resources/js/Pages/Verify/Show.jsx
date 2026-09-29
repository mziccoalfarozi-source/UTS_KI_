import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const RESULT_CONFIG = {
    VALID: {
        bg: 'bg-emerald-50',
        border: 'border-emerald-200',
        icon: 'text-emerald-700',
        text: 'text-emerald-800',
        iconChar: '✓',
        label: 'Dokumen Valid',
    },
    INCOMPLETE: {
        bg: 'bg-amber-50',
        border: 'border-amber-200',
        icon: 'text-amber-600',
        text: 'text-amber-800',
        iconChar: '⚠',
        label: null, // built dynamically
    },
    INVALID_DOCUMENT_MODIFIED: {
        bg: 'bg-red-50',
        border: 'border-red-200',
        icon: 'text-red-700',
        text: 'text-red-800',
        iconChar: '✗',
        label: 'Dokumen Telah Diubah',
    },
    INVALID_SIGNATURE: {
        bg: 'bg-red-50',
        border: 'border-red-200',
        icon: 'text-red-700',
        text: 'text-red-800',
        iconChar: '✗',
        label: 'Tanda Tangan Tidak Valid',
    },
    INVALID_PUBLIC_KEY: {
        bg: 'bg-red-50',
        border: 'border-red-200',
        icon: 'text-red-700',
        text: 'text-red-800',
        iconChar: '✗',
        label: 'Public Key Tidak Valid',
    },
    REJECTED_TOKEN_NOT_FOUND: {
        bg: 'bg-red-50',
        border: 'border-red-200',
        icon: 'text-red-700',
        text: 'text-red-800',
        iconChar: '✗',
        label: 'Token Tidak Ditemukan',
    },
};

export default function VerifyShow({ token, result, document }) {
    const [showAdvanced, setShowAdvanced] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        pdf: null,
        document_signer_id: '',
        custom_public_key: '',
    });

    function submitUpload(e) {
        e.preventDefault();
        post(`/verify/${token}`, { forceFormData: true });
    }

    const cfg = RESULT_CONFIG[result.code] ?? RESULT_CONFIG.INVALID_SIGNATURE;
    const resultLabel =
        result.code === 'INCOMPLETE'
            ? `Dokumen Belum Lengkap (${result.signed_count}/${result.total_signers})`
            : cfg.label;

    const signedSigners = document?.signers?.filter((s) => s.status === 'SIGNED') ?? [];

    return (
        <>
            <Head title="Hasil Verifikasi" />
            <main className="min-h-screen bg-zinc-100">
                {/* Branding header — public, no nav */}
                <header className="border-b border-zinc-200 bg-white">
                    <div className="mx-auto flex max-w-3xl items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                            <h1 className="text-lg font-semibold">Hasil Verifikasi</h1>
                        </div>
                        <Link href="/verify" className="text-sm font-semibold text-zinc-700 hover:text-zinc-950">
                            Verifikasi token lain
                        </Link>
                    </div>
                </header>

                <div className="mx-auto flex max-w-3xl flex-col gap-6 px-5 py-8">
                    {/* Result banner */}
                    <div
                        className={`flex items-start gap-4 border p-5 shadow-sm ${cfg.bg} ${cfg.border}`}
                    >
                        <span className={`mt-0.5 text-2xl leading-none font-bold ${cfg.icon}`}>
                            {cfg.iconChar}
                        </span>
                        <div className="flex flex-col gap-1">
                            <p className={`text-base font-semibold ${cfg.text}`}>{resultLabel}</p>
                            <p className={`font-mono text-xs break-all ${cfg.text} opacity-70`}>{token}</p>
                        </div>
                    </div>

                    {/* Document info */}
                    {document !== null && (
                        <>
                            <section className="border border-zinc-200 bg-white p-6 shadow-sm">
                                <div className="flex flex-col gap-1 border-b border-zinc-200 pb-5">
                                    <h2 className="text-xl font-semibold">{document.title}</h2>
                                    <p className="text-sm text-zinc-600">{document.institution}</p>
                                </div>
                                <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                                    <Item label="Tanggal" value={document.document_date} />
                                    <Item label="Status" value={document.status} />
                                    <div className="sm:col-span-2">
                                        <Item label="Document hash" value={document.document_hash} mono />
                                    </div>
                                </dl>
                            </section>

                            {/* Signers table */}
                            <section>
                                <h2 className="mb-3 text-base font-semibold">Daftar penandatangan</h2>
                                <div className="overflow-x-auto border border-zinc-200 bg-white shadow-sm">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase text-zinc-500">
                                            <tr>
                                                <th className="px-4 py-3">Urutan</th>
                                                <th className="px-4 py-3">Nama</th>
                                                <th className="px-4 py-3">Jabatan</th>
                                                <th className="px-4 py-3">Status</th>
                                                <th className="px-4 py-3">Waktu tanda tangan</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-zinc-200">
                                            {document.signers.map((signer) => (
                                                <tr key={signer.id}>
                                                    <td className="px-4 py-3">{signer.sign_order}</td>
                                                    <td className="px-4 py-3 font-medium">{signer.name}</td>
                                                    <td className="px-4 py-3">{signer.position_title}</td>
                                                    <td className="px-4 py-3">
                                                        <StatusBadge status={signer.status} />
                                                    </td>
                                                    <td className="px-4 py-3 text-zinc-500">
                                                        {signer.signed_at
                                                            ? new Date(signer.signed_at).toLocaleString('id-ID')
                                                            : '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            {/* Upload verification form */}
                            <section className="border border-zinc-200 bg-white p-6 shadow-sm">
                                <div className="flex flex-col gap-1">
                                    <h2 className="text-base font-semibold">Verifikasi dengan Upload PDF</h2>
                                    <p className="text-sm text-zinc-600">
                                        Upload file PDF asli untuk memverifikasi kriptografi secara langsung.
                                    </p>
                                </div>

                                <form className="mt-5 flex flex-col gap-5" onSubmit={submitUpload}>
                                    <label className="flex flex-col gap-2 text-sm font-medium">
                                        File PDF
                                        <input
                                            type="file"
                                            accept="application/pdf"
                                            onChange={(e) => setData('pdf', e.target.files[0] ?? null)}
                                            required
                                            className="block w-full text-sm file:mr-3 file:border-0 file:bg-zinc-900 file:px-3 file:py-2 file:font-semibold file:text-white"
                                        />
                                        {errors.pdf && (
                                            <span className="font-normal text-red-700">{errors.pdf}</span>
                                        )}
                                    </label>

                                    {/* Advanced section */}
                                    <div className="border border-zinc-200">
                                        <button
                                            type="button"
                                            onClick={() => setShowAdvanced((v) => !v)}
                                            className="flex w-full items-center justify-between px-4 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-50"
                                        >
                                            <span>Advanced Verification</span>
                                            <span className="text-zinc-400">{showAdvanced ? '▲' : '▼'}</span>
                                        </button>

                                        {showAdvanced && (
                                            <div className="flex flex-col gap-5 border-t border-zinc-200 p-4">
                                                {signedSigners.length > 0 && (
                                                    <label className="flex flex-col gap-2 text-sm font-medium">
                                                        Target signer
                                                        <select
                                                            value={data.document_signer_id}
                                                            onChange={(e) =>
                                                                setData('document_signer_id', e.target.value)
                                                            }
                                                            className="h-10 border border-zinc-300 px-3 outline-none focus:border-emerald-700"
                                                        >
                                                            <option value="">— Pilih signer —</option>
                                                            {signedSigners.map((signer) => (
                                                                <option key={signer.id} value={signer.id}>
                                                                    {signer.sign_order}. {signer.name} —{' '}
                                                                    {signer.position_title}
                                                                </option>
                                                            ))}
                                                        </select>
                                                        {errors.document_signer_id && (
                                                            <span className="font-normal text-red-700">
                                                                {errors.document_signer_id}
                                                            </span>
                                                        )}
                                                    </label>
                                                )}

                                                <label className="flex flex-col gap-2 text-sm font-medium">
                                                    Custom public key (PEM)
                                                    <textarea
                                                        value={data.custom_public_key}
                                                        onChange={(e) =>
                                                            setData('custom_public_key', e.target.value)
                                                        }
                                                        rows={6}
                                                        placeholder="-----BEGIN PUBLIC KEY-----&#10;...&#10;-----END PUBLIC KEY-----"
                                                        className="border border-zinc-300 px-3 py-2 font-mono text-xs outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100"
                                                    />
                                                    {errors.custom_public_key && (
                                                        <span className="font-normal text-red-700">
                                                            {errors.custom_public_key}
                                                        </span>
                                                    )}
                                                </label>
                                            </div>
                                        )}
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="h-11 bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {processing ? 'Memverifikasi...' : 'Verifikasi dengan Upload'}
                                    </button>
                                </form>
                            </section>
                        </>
                    )}
                </div>
            </main>
        </>
    );
}

function StatusBadge({ status }) {
    if (status === 'SIGNED') {
        return (
            <span className="inline-flex items-center gap-1 bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                ✓ SIGNED
            </span>
        );
    }
    return (
        <span className="inline-flex items-center gap-1 bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-600">
            PENDING
        </span>
    );
}

function Item({ label, value, mono = false }) {
    return (
        <div className="flex min-w-0 flex-col gap-1">
            <dt className="text-xs font-semibold uppercase text-zinc-500">{label}</dt>
            <dd className={`break-all text-sm font-medium ${mono ? 'font-mono' : ''}`}>{value}</dd>
        </div>
    );
}
