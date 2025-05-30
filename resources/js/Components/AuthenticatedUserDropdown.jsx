import { Link } from '@inertiajs/react';

export default function AuthenticatedUserDropdown({ user }) {
    return (
        <div className="dropdown dropdown-end">
            <label tabIndex={0} className="btn btn-primary  normal-case inline-flex items-center rounded-md border border-transparent px-3 py-2 text-sm font-medium leading-4 text-gray-500 hover:text-gray-700 focus:outline-none transition duration-150 ease-in-out">
                {user.name}
                <svg
                    className="ms-2 -me-0.5 h-4 w-4" // Tailwind class names are fine here
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                >
                    <path
                        fillRule="evenodd"
                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        clipRule="evenodd"
                    />
                </svg>
            </label>
            <ul tabIndex={0} className="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-48">
                <li>
                    <Link href={route('profile.edit')}>個人設定</Link>
                </li>
                <li>
                    <Link href={route('logout')} method="post" as="button">
                        ログアウト
                    </Link>
                </li>
            </ul>
        </div>
    );
}