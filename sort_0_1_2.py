"""Question is to sort the given array contains of 0's and 1's and 2's without using predefined 
sorting algorithm"""

def sort_the_arr(arr):
    l = 0 
    h = len(arr) -1 
    for m in range(len(arr)):
        if arr[m] == 0:
            arr[l] , arr[m] = arr[m], arr[l]
            l += 1
        if arr[m] == 2:
            arr[m] , arr[h] = arr[h], arr[m]
            h -= 1
    return arr
print(sort_the_arr([0, 1 , 1, 0, 2, 0 , 1, 2, 1, 0]))