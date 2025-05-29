import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="Welcome" />
            <div className="flex flex-col min-h-screen">
                <header>
                    <nav className='navbar shadow-sm'>
                        <div className="flex-none">
                            <button className='btn btn-square btn-ghost'>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" className="inline-block h-5 w-5 stroke-current"> <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"></path> </svg> 
                            </button>
                        </div>
                        <div className="flex-1">
                            <a className="btn btn-ghost text-xl">Hayonaos</a>
                        </div>
                        <div className="flex-none">
                        {auth.user ? (
                            <Link href={route('dashboard')}>
                                <button className="btn btn-square btn-ghost">Dashboard</button>
                            </Link>
                        ) : (
                            <>
                                <ul className="menu menu-horizontal px-1">
                                    <li><Link href={route('login')}>ログイン</Link></li>
                                    <li><Link href={route('register')}>新規登録</Link></li>
                                </ul>
                            </>
                        )}
                        </div>
                    </nav>
                </header>

                <main className='flex flex-grow'>
                    <div className="hero bg-base-200">
                        <div className="hero-content text-center">
                            <div className="max-w-md">
                                <div className="py-12 text-7xl">
                                    📦
                                </div>
                                <h1 className="text-5xl font-bold">
                                    <span className="font-thin">はよなおしーや。</span>
                                    Hayonaos.
                                </h1>
                                <p className="py-6 font-thin">だるい片付けのおともにいかがですか？</p>
                            </div>
                        </div>
                    </div>
                </main>

                <footer className='footer footer-center border-base-200 bg-base-200 text-base-content border-t px-4 py-12'>
                    <div>
                        &copy; 2025 Hondenas Corp.
                    </div>
                </footer>
            </div>
        </>
    );
}
