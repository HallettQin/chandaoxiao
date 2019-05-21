<template>
  <div class="chat">
    <h1>{{ msg }}</h1>
  </div>
</template>

<script>
  export default {
    name: 'chat',
    data: function () {
      return {
        msg: 'Welcome to chat App'
      }
    }
  }
</script>
