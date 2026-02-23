// ═══════════════════════════════════════════════════════════════════
// SESSION CHECKER - Include this in protected pages
// ═══════════════════════════════════════════════════════════════════

// Supabase Configuration
const SUPABASE_URL = 'https://dljukwzdbkxkbngiqzmm.supabase.co';
const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImRsanVrd3pkYmt4a2JuZ2lxem1tIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzE1MDIwMDksImV4cCI6MjA4NzA3ODAwOX0.DohlZ6nJYcJdg8BuulJD52OKFQwyP07-c_htkvmsMyA';

const { createClient } = supabase;
const supabaseClient = createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Store global user session
let currentUser = null;

/**
 * Check if user is authenticated and get session
 * Redirects to login if not authenticated
 */
async function checkAuthSession() {
  try {
    const { data, error } = await supabaseClient.auth.getSession();

    if (error || !data.session) {
      // No session found, redirect to login
      window.location.href = 'index.html';
      return null;
    }

    currentUser = data.session.user;
    
    // Get user profile from database
    const { data: profile, error: profileError } = await supabaseClient
      .from('youth_profiles')
      .select('*')
      .eq('user_id', currentUser.id)
      .single();

    if (profile) {
      currentUser.profile = profile;
      localStorage.setItem('user_profile', JSON.stringify(profile));
    }

    return currentUser;

  } catch (error) {
    console.error('Session check error:', error);
    window.location.href = 'index.html';
    return null;
  }
}

/**
 * Get current user data
 */
function getCurrentUser() {
  return currentUser;
}

/**
 * Get current user's profile
 */
async function getUserProfile() {
  if (!currentUser) {
    return null;
  }

  try {
    const { data, error } = await supabaseClient
      .from('youth_profiles')
      .select('*')
      .eq('user_id', currentUser.id)
      .single();

    return data;
  } catch (error) {
    console.error('Profile fetch error:', error);
    return null;
  }
}

/**
 * Logout user and redirect to login
 */
async function logoutUser() {
  try {
    await supabaseClient.auth.signOut();
    localStorage.removeItem('user_profile');
    currentUser = null;
    window.location.href = 'index.html';
  } catch (error) {
    console.error('Logout error:', error);
    window.location.href = 'index.html';
  }
}

/**
 * Update user profile
 */
async function updateUserProfile(updates) {
  if (!currentUser) {
    throw new Error('No user session');
  }

  try {
    const { data, error } = await supabaseClient
      .from('youth_profiles')
      .update(updates)
      .eq('user_id', currentUser.id)
      .select()
      .single();

    if (error) {
      throw new Error(error.message);
    }

    currentUser.profile = data;
    localStorage.setItem('user_profile', JSON.stringify(data));

    return data;
  } catch (error) {
    console.error('Profile update error:', error);
    throw error;
  }
}

/**
 * Update user email or password
 */
async function updateUserAuth(updates) {
  try {
    const { data, error } = await supabaseClient.auth.updateUser(updates);

    if (error) {
      throw new Error(error.message);
    }

    currentUser = data.user;
    return data;
  } catch (error) {
    console.error('Auth update error:', error);
    throw error;
  }
}
