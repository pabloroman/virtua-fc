export async function onRequestPost(context) {
    const { request, env } = context;

    const headers = {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*',
    };

    try {
        const { name, email } = await request.json();

        if (!name || !email) {
            return new Response(JSON.stringify({ error: 'Name and email are required.' }), {
                status: 400,
                headers,
            });
        }

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            return new Response(JSON.stringify({ error: 'Invalid email address.' }), {
                status: 400,
                headers,
            });
        }

        const existing = await env.DB.prepare(
            'SELECT id FROM waitlist WHERE email = ?'
        ).bind(email).first();

        if (existing) {
            return new Response(JSON.stringify({ message: "You're already on the list!" }), {
                status: 200,
                headers,
            });
        }

        await env.DB.prepare(
            'INSERT INTO waitlist (name, email, created_at) VALUES (?, ?, ?)'
        ).bind(name, email, new Date().toISOString()).run();

        return new Response(JSON.stringify({ message: 'Successfully joined the waitlist!' }), {
            status: 201,
            headers,
        });
    } catch (err) {
        return new Response(JSON.stringify({ error: 'Internal server error.' }), {
            status: 500,
            headers,
        });
    }
}

export async function onRequestOptions() {
    return new Response(null, {
        headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'POST, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type',
        },
    });
}
