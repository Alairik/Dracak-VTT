// Sdílená auth vrstva pro editor.html i budoucí propojení s mapou (Torch).
// Očekává, že config.js už nastavil window.DRACAK_CONFIG a že stránka
// načetla @supabase/supabase-js z CDN před tímto souborem.

const { SUPABASE_URL, SUPABASE_ANON_KEY } = window.DRACAK_CONFIG;

window.dracakClient = (SUPABASE_URL && SUPABASE_ANON_KEY)
  ? supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY)
  : null;

async function dracakGetSession() {
  if (!window.dracakClient) return null;
  const { data } = await window.dracakClient.auth.getSession();
  return data.session ?? null;
}

async function dracakGetProfile(userId) {
  if (!window.dracakClient || !userId) return null;
  const { data, error } = await window.dracakClient
    .from('profiles')
    .select('id, display_name, role, editable_types')
    .eq('id', userId)
    .single();
  if (error) {
    console.error('Nepodařilo se načíst profil:', error.message);
    return null;
  }
  return data;
}

async function dracakSignIn(email, password) {
  if (!window.dracakClient) throw new Error('Supabase není nakonfigurované (viz assets/js/config.js).');
  const { error } = await window.dracakClient.auth.signInWithPassword({ email, password });
  if (error) throw error;
}

async function dracakSignOut() {
  if (!window.dracakClient) return;
  await window.dracakClient.auth.signOut();
}

// Ochrana stránky: přesměruje na login, pokud uživatel není přihlášený.
// Vrací { session, profile } pro stránky, které to potřebují.
async function dracakRequireAuth() {
  const session = await dracakGetSession();
  if (!session) {
    window.location.href = 'index.html';
    return null;
  }
  const profile = await dracakGetProfile(session.user.id);
  return { session, profile };
}
