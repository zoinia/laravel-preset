<template>
  <div>
    <portal-target name="dropdown" slim />
    <div class="flex flex-col">
      <div class="min-h-screen flex flex-col" @click="hideDropdownMenus">
        <div class="md:flex">
          <div class="bg-primary-900 md:flex-shrink-0 md:w-56 px-6 py-4 flex items-center justify-between md:justify-center">
            <inertia-link class="mt-1" href="/">
              <logo class="text-white fill-current" width="120" height="28" />
            </inertia-link>
            <dropdown class="md:hidden" placement="bottom-end">
              <svg class="text-white fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M0 3h20v2H0V3zm0 6h20v2H0V9zm0 6h20v2H0v-2z" /></svg>
              <div slot="dropdown" class="mt-2 px-8 py-4 shadow-xl bg-primary-800 rounded">
                <main-menu />
              </div>
            </dropdown>
          </div>
          <div class="bg-white border-b w-full p-4 md:py-0 md:px-12 text-sm md:text-md flex justify-between items-center">
            <div class="mt-1 mr-4">{{ $page.app.name }}</div>
            <dropdown class="mt-1" placement="bottom-end">
              <div class="flex items-center cursor-pointer select-none group">
                <div class="text-gray-900 group-hover:text-primary-700 focus:text-primary-700 mr-1 whitespace-no-wrap">
                  <span>{{ $page.auth.user.name }}</span>
                  <!-- <span class="hidden md:inline">USER LAST NAME</span> -->
                </div>
                <icon class="w-5 h-5 group-hover:fill-primary-700 fill-gray-900 focus:fill-primary-700" name="cheveron-down" />
              </div>
              <div slot="dropdown" class="mt-2 py-2 shadow-xl bg-white rounded text-sm">
                <inertia-link class="block px-6 py-2 hover:bg-primary-500 hover:text-white" :href="route('users.edit', $page.auth.user.id)">My Profile</inertia-link>
                <inertia-link class="block px-6 py-2 hover:bg-primary-500 hover:text-white" :href="route('users')">Manage Users</inertia-link>
                <inertia-link class="block px-6 py-2 hover:bg-primary-500 hover:text-white" :href="route('logout')" method="post">Logout</inertia-link>
              </div>
            </dropdown>
          </div>
        </div>
        <div class="flex flex-grow">
          <div class="bg-primary-800 flex-shrink-0 w-56 p-12 hidden md:block">
            <main-menu />
          </div>
          <div class="w-full overflow-hidden px-4 py-8 md:p-12">
            <slot />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Dropdown from '@/Shared/Dropdown'
import Icon from '@/Shared/Icon'
import Logo from '@/Shared/Logo'
import MainMenu from '@/Shared/MainMenu'

export default {
  components: {
    Dropdown,
    Icon,
    Logo,
    MainMenu,
  },
  props: {
    title: String,
  },
  data() {
    return {
      showUserMenu: false,
      accounts: null,
    }
  },
  watch: {
    title(title) {
      this.updatePageTitle(title)
    },
  },
  mounted() {
    this.updatePageTitle(this.title)
  },
  methods: {
    updatePageTitle(title) {
      //document.title = title ? `${title} | ${this.$page.app.name}` : this.$page.app.name
      document.title = this.title
    },
    hideDropdownMenus() {
      this.showUserMenu = false
    },
  },
}
</script>